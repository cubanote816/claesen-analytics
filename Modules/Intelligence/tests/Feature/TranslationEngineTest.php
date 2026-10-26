<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Models\Site;
use Modules\Intelligence\Jobs\TranslateModelAttributesJob;
use Modules\Intelligence\Models\Glossary;
use Modules\Intelligence\Models\TranslationState;
use Modules\Intelligence\Services\GeminiService;
use Modules\Intelligence\Services\GlossaryService;
use Modules\Intelligence\Services\GoogleServiceAccountAuthService;
use Modules\Intelligence\Services\TranslationStateService;
use Modules\Website\Models\LegalDocument;
use Tests\TestCase;

/**
 * CLA-611: the reliable per-locale translation engine.
 *
 * Regression tests for the gaps the brief enumerates:
 *  - G1: a source-text change marks derived locales stale and retranslates them;
 *  - G3: a provider failure leaves an observable `failed` state;
 *  - G5: reviewed|published values are never overwritten (needs_review);
 *  - G4/G6: glossary + context are injected and identical work is cached;
 *  - G7: batching is capped at 20 source strings per provider call;
 *  - derived public status: one unapproved key keeps the entity unapproved.
 */
final class TranslationEngineTest extends TestCase
{
    use RefreshDatabase;

    private TranslationStateService $states;

    protected function setUp(): void
    {
        parent::setUp();

        // DB::afterCommit dispatches are inert inside RefreshDatabase's
        // wrapping transaction — the hook's synchronous state invalidation
        // is what these tests observe, and the job is run explicitly.
        $this->states = app(TranslationStateService::class);

        // Save-triggered dispatches must not run the real job (QUEUE_CONNECTION
        // is sync and afterCommit fires here) — tests invoke ->handle() explicitly.
        Queue::fake();

        // The hook's source-locale logic is the request/editor locale — pin it
        // so tests do not depend on APP_LOCALE.
        app()->setLocale('nl');
    }

    private function makeDoc(string $nlTitle = 'Privacyverklaring'): LegalDocument
    {
        return LegalDocument::create([
            'site_id' => Site::claesenId(),
            'doc_id' => 'privacy',
            'title' => ['nl' => $nlTitle],
            'body' => ['nl' => 'Body.'],
            'version' => '2026-10-01',
            'effective_date' => '2026-10-01',
        ]);
    }

    private function job(LegalDocument $doc, string $sourceLocale = 'nl'): TranslateModelAttributesJob
    {
        return new TranslateModelAttributesJob(LegalDocument::class, $doc->id, ['title', 'body'], $sourceLocale);
    }

    // ── G1: source change → stale → retranslate ─────────────────────────────

    public function test_a_source_text_change_marks_machine_locales_stale_and_retranslates_them(): void
    {
        $doc = $this->makeDoc();

        $doc->setTranslation('title', 'en', 'Old privacy statement');
        $doc->save();
        $doc->refresh();

        $this->states->recordState($doc, 'title', 'en', TranslationState::STATUS_MACHINE, 'nl', hash('sha256', 'Privacyverklaring'), 0);

        app()->setLocale('nl');
        $doc->setTranslation('title', 'nl', 'Privacyverklaring 2026');
        $doc->save();
        $doc->refresh();

        // Hook synchronously marked the derived locale stale...
        $this->assertSame(
            TranslationState::STATUS_STALE,
            $this->states->stateFor($doc, 'title', 'en')?->status
        );

        // ...and the job retranslates exactly the stale locale (G1: no
        // forever-outdated translation, no silence).
        $gemini = $this->mock(GeminiService::class);
        $gemini->shouldReceive('translateAndDetect')
            ->andReturnUsing(function (string $text, array $locales) {
                $this->assertContains('en', $locales);
                $this->assertSame('Privacyverklaring 2026', $text);

                return [
                    'detected_locale' => 'nl',
                    'translations' => array_fill_keys($locales, 'Fresh translation'),
                ];
            });

        $this->job($doc)->handle($gemini);

        $doc->refresh();
        $this->assertSame('Fresh translation', $doc->getTranslation('title', 'en', false));
        $this->assertSame(
            TranslationState::STATUS_MACHINE,
            $this->states->stateFor($doc, 'title', 'en')?->status
        );
        $this->assertSame(
            hash('sha256', 'Privacyverklaring 2026'),
            $this->states->stateFor($doc, 'title', 'en')?->source_hash
        );
    }

    // ── G5: reviewed values are never overwritten ────────────────────────────

    public function test_a_reviewed_value_is_never_overwritten_but_flagged_needs_review(): void
    {
        $doc = $this->makeDoc();

        foreach (['en' => 'Privacy statement', 'fr' => 'Déclaration', 'de' => 'Erklärung'] as $locale => $value) {
            $doc->setTranslation('title', $locale, $value);
            $doc->setTranslation('body', $locale, 'Body '.$locale);
            $this->states->recordState($doc, 'title', $locale, TranslationState::STATUS_REVIEWED, 'nl', hash('sha256', 'Privacyverklaring'), 0);
        }

        $doc->save();
        $doc->refresh();

        app()->setLocale('nl');
        $doc->setTranslation('title', 'nl', 'Privacyverklaring 2026');
        $doc->save();
        $doc->refresh();

        // Approved values preserved (needs_review), not stale, not requeued.
        $this->assertSame(
            TranslationState::STATUS_NEEDS_REVIEW,
            $this->states->stateFor($doc, 'title', 'en')?->status
        );

        $gemini = $this->mock(GeminiService::class);
        $gemini->shouldReceive('translateAndDetect')
            ->andReturn(['detected_locale' => 'nl', 'translations' => []]);

        $this->job($doc)->handle($gemini);

        $doc->refresh();
        $this->assertSame('Privacy statement', $doc->getTranslation('title', 'en', false));
        $this->assertSame(
            TranslationState::STATUS_NEEDS_REVIEW,
            $this->states->stateFor($doc, 'title', 'en')?->status
        );
    }

    // ── G3: provider failure is observable ───────────────────────────────────

    public function test_a_provider_failure_leaves_a_visible_failed_state(): void
    {
        $doc = $this->makeDoc();

        $gemini = $this->mock(GeminiService::class);
        $gemini->expects('translateAndDetect')
            ->twice() // title AND body both have a nl source
            ->andThrow(new \Exception('Gemini API unavailable'));

        $this->job($doc)->handle($gemini);

        foreach (['en', 'fr', 'de'] as $locale) {
            $state = $this->states->stateFor($doc, 'title', $locale);

            $this->assertNotNull($state, "Missing failed state for [{$locale}].");
            $this->assertSame(TranslationState::STATUS_FAILED, $state->status);
            $this->assertStringContainsString('Gemini API unavailable', (string) $state->error);
        }
    }

    // ── Derived public status: one unapproved key blocks approval ────────────

    public function test_one_unapproved_key_keeps_the_entity_unapproved_in_a_locale(): void
    {
        $doc = $this->makeDoc();

        $this->states->recordState($doc, 'title', 'fr', TranslationState::STATUS_REVIEWED, 'nl', 'x', 0);
        $this->states->recordState($doc, 'body', 'fr', TranslationState::STATUS_MACHINE, 'nl', 'x', 0);

        // Worst-of rule: body is only machine → the locale is NOT approved.
        $this->assertSame(
            TranslationState::STATUS_MACHINE,
            $doc->translationStatusFor('fr')
        );

        $this->states->recordState($doc, 'body', 'fr', TranslationState::STATUS_PUBLISHED, 'nl', 'x', 0);

        // Worst-of rule: [reviewed, published] → reviewed — still inside the
        // publishable set {reviewed, published}.
        $this->assertSame(
            TranslationState::STATUS_REVIEWED,
            $doc->translationStatusFor('fr')
        );
    }

    public function test_legacy_data_without_state_rows_is_never_silently_approved(): void
    {
        $doc = $this->makeDoc(); // title/body filled in nl only

        $this->assertSame(TranslationState::STATUS_MISSING, $doc->translationStatusFor('fr'));
        $this->assertSame(TranslationState::STATUS_MACHINE, $doc->translationStatusFor('nl'));
    }

    // ── G4/G6: glossary + context in the prompt, cache on repeat ─────────────

    public function test_the_prompt_carries_glossary_and_context_and_repeats_are_cached(): void
    {
        Glossary::create([
            'site_id' => Site::claesenId(),
            'term' => 'KNX',
            'translations' => [],
            'do_not_translate' => true,
        ]);
        Glossary::create([
            'site_id' => Site::claesenId(),
            'term' => 'verdeelbord',
            'translations' => ['fr' => 'tableau de distribution'],
            'do_not_translate' => false,
        ]);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'detected_locale' => 'nl',
                            'translations' => ['fr' => 'tableau de distribution'],
                        ]),
                    ]]],
                ]],
            ]),
        ]);

        $service = new GeminiService($this->mock(GoogleServiceAccountAuthService::class, function ($mock): void {
            $mock->shouldReceive('getAccessToken')->andReturn('fake-token');
        }));

        $glossary = app(GlossaryService::class)->forSite(Site::claesenId());

        $first = $service->translateAndDetect('Het verdeelbord met KNX', ['fr'], 'legal body text', $glossary['terms'], $glossary['version']);
        $second = $service->translateAndDetect('Het verdeelbord met KNX', ['fr'], 'legal body text', $glossary['terms'], $glossary['version']);

        $this->assertSame(['fr' => 'tableau de distribution'], $first['translations']);
        $this->assertSame($first, $second);

        // ONE provider call for two identical requests (G6) — and the call
        // carried the glossary rules and the context (G4).
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $prompt = $request['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($prompt, '"KNX" is a brand name')
                && str_contains($prompt, 'tableau de distribution')
                && str_contains($prompt, 'legal body text');
        });
    }

    public function test_editing_the_glossary_invalidates_the_translation_cache(): void
    {
        Glossary::create([
            'site_id' => Site::claesenId(),
            'term' => 'verdeelbord',
            'translations' => ['fr' => 'tableau de distribution'],
            'do_not_translate' => false,
        ]);

        $glossaryService = app(GlossaryService::class);
        $before = $glossaryService->forSite(Site::claesenId())['version'];

        Glossary::query()->where('term', 'verdeelbord')->first()->update(['translations' => ['fr' => 'coffret de distribution']]);
        GlossaryService::forgetCacheFor(Site::claesenId());

        $after = $glossaryService->forSite(Site::claesenId())['version'];

        $this->assertNotSame($before, $after);
        $this->assertSame(
            ['fr' => 'coffret de distribution'],
            $glossaryService->forSite(Site::claesenId())['terms']['verdeelbord']['translations']
        );
    }

    // ── G7: batching is capped and resumable ─────────────────────────────────

    public function test_translate_batch_rejects_more_than_twenty_strings_per_call(): void
    {
        $service = new GeminiService($this->mock(GoogleServiceAccountAuthService::class));

        $this->expectException(\InvalidArgumentException::class);

        $service->translateBatch(array_fill(0, 21, 'text'), 'fr');
    }

    public function test_the_pending_command_translates_stale_states_in_batches_and_resumes(): void
    {
        $doc = $this->makeDoc();
        $doc->setTranslation('title', 'en', 'Stale statement');
        $doc->save();
        $doc->refresh();

        $this->states->recordState($doc, 'title', 'en', TranslationState::STATUS_STALE, 'nl', 'x', 0);

        $calls = [];
        $gemini = $this->mock(GeminiService::class);
        $gemini->shouldReceive('translateBatch')
            ->andReturnUsing(function (array $texts, string $locale) use (&$calls) {
                $calls[] = [$texts, $locale];

                return [key($texts) => [$locale => 'Fresh statement']];
            });

        $this->artisan('intelligence:translate-pending')->assertSuccessful();

        $doc->refresh();
        $this->assertSame('Fresh statement', $doc->getTranslation('title', 'en', false));
        $this->assertSame(
            TranslationState::STATUS_MACHINE,
            $this->states->stateFor($doc, 'title', 'en')?->status
        );

        // Exactly one batched provider call for one pending item.
        $this->assertCount(1, $calls, 'translateBatch calls: '.var_export($calls, true));

        // Resumable: nothing pending left, second run is a no-op.
        $calls = [];
        $this->artisan('intelligence:translate-pending')->assertSuccessful();
        $this->assertCount(0, $calls, 'resume should not call the provider');
    }
}
