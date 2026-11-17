<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Models\Site;
use Modules\Intelligence\Exceptions\GeminiProviderException;
use Modules\Intelligence\Jobs\TranslateModelAttributesJob;
use Modules\Intelligence\Models\TranslationState;
use Modules\Intelligence\Services\GeminiService;
use Modules\Intelligence\Services\GoogleServiceAccountAuthService;
use Modules\Intelligence\Services\TranslationStateService;
use Modules\Website\Models\LegalDocument;
use Tests\TestCase;

/**
 * CLA-694: translation engine reliability.
 *
 * Runs the REAL GeminiService against a faked HTTP layer ("echoing model")
 * to prove: batch numbering is position-based, provider failures propagate
 * and are never cached, and the job retries failed locales.
 */
final class TranslationEngineReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private TranslationStateService $states;

    protected function setUp(): void
    {
        parent::setUp();

        $this->states = app(TranslationStateService::class);
        Queue::fake();
        app()->setLocale('nl');

        $this->mock(GoogleServiceAccountAuthService::class, function ($mock): void {
            $mock->shouldReceive('getAccessToken')->andReturn('fake-token');
        });

        Http::preventStrayRequests();
    }

    private function wrap(array $payload): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($payload)]]]]]];
    }

    /** Echoing model: answers batch and single prompts deterministically. */
    private function echoModel(): \Closure
    {
        return function (Request $request) {
            $prompt = $request['contents'][0]['parts'][0]['text'] ?? '';

            if (str_starts_with($prompt, 'Translate each numbered source string to ')) {
                preg_match('/^Translate each numbered source string to (\w+)\./', $prompt, $m);
                $locale = $m[1];
                $translations = [];

                foreach (explode("\n", $prompt) as $line) {
                    if (preg_match('/^(\d+)\. (.*)$/', $line, $mm)) {
                        $translations[$mm[1]] = $mm[2].' ['.$locale.']';
                    }
                }

                return Http::response($this->wrap(['translations' => $translations]));
            }

            if (preg_match('/Detect source language of "(.*)" and translate to: ([^.\n]+)\./s', $prompt, $m)) {
                $translations = [];

                foreach (array_map('trim', explode(',', $m[2])) as $locale) {
                    $translations[$locale] = $m[1].' ['.$locale.']';
                }

                return Http::response($this->wrap(['detected_locale' => 'nl', 'translations' => $translations]));
            }

            return Http::response($this->wrap([]));
        };
    }

    /** Provider that fails with HTTP 500 while $state->down is true, else echoes. */
    private function switchableProvider(): \stdClass
    {
        $state = new \stdClass;
        $state->down = true;
        $echo = $this->echoModel();

        Http::fake(fn (Request $request) => $state->down ? Http::response('boom', 500) : $echo($request));

        return $state;
    }

    private function service(): GeminiService
    {
        return app(GeminiService::class);
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

    private function job(LegalDocument $doc): TranslateModelAttributesJob
    {
        return new TranslateModelAttributesJob(LegalDocument::class, $doc->id, ['title', 'body'], 'nl');
    }

    // 1. Cache hit between two pending items.
    public function test_batch_with_a_cached_item_between_pending_items_maps_each_translation_correctly(): void
    {
        Http::fake($this->echoModel());

        // Warm the cache for "Beta" only.
        $this->service()->translateBatch([0 => 'Beta'], 'en');
        Http::assertSentCount(1);

        $result = $this->service()->translateBatch([0 => 'Alpha', 1 => 'Beta', 2 => 'Gamma'], 'en');

        $this->assertSame('Alpha [en]', $result[0]['en']);
        $this->assertSame('Beta [en]', $result[1]['en']);
        $this->assertSame('Gamma [en]', $result[2]['en']);
        Http::assertSentCount(2);
    }

    // 2. Non-sequential keys.
    public function test_batch_keyed_by_non_sequential_ids_maps_each_translation_correctly(): void
    {
        Http::fake($this->echoModel());

        $result = $this->service()->translateBatch([11 => 'A', 12 => 'B'], 'fr');

        $this->assertSame(['fr' => 'A [fr]'], $result[11]);
        $this->assertSame(['fr' => 'B [fr]'], $result[12]);
    }

    // 3. Command end-to-end with the real service.
    public function test_pending_command_end_to_end_translates_a_stale_state(): void
    {
        Http::fake($this->echoModel());

        $doc = $this->makeDoc();
        $doc->setTranslation('title', 'en', 'Stale statement');
        $doc->save();
        $doc->refresh();
        $this->states->recordState($doc, 'title', 'en', TranslationState::STATUS_STALE, 'nl', 'x', 0);

        $this->artisan('intelligence:translate-pending')->assertSuccessful();

        $doc->refresh();
        $this->assertSame('Privacyverklaring [en]', $doc->getTranslation('title', 'en', false));
        $this->assertSame(TranslationState::STATUS_MACHINE, $this->states->stateFor($doc, 'title', 'en')?->status);
    }

    // 4. HTTP 500 propagates.
    public function test_translate_and_detect_throws_on_http_failure(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->expectException(GeminiProviderException::class);
        $this->expectExceptionMessage('HTTP 500');

        $this->service()->translateAndDetect('Hallo', ['en']);
    }

    // 5. A failed call is not cached.
    public function test_a_failed_call_is_not_cached(): void
    {
        Http::fakeSequence()
            ->push('boom', 500)
            ->push($this->wrap(['detected_locale' => 'nl', 'translations' => ['en' => 'Hello']]));

        try {
            $this->service()->translateAndDetect('Hallo', ['en']);
            $this->fail('Expected GeminiProviderException.');
        } catch (GeminiProviderException) {
            // expected
        }

        $result = $this->service()->translateAndDetect('Hallo', ['en']);

        $this->assertSame(['en' => 'Hello'], $result['translations']);
        Http::assertSentCount(2);
    }

    // 6. Job: real HTTP failure rethrows, states failed, retry succeeds.
    public function test_job_rethrows_provider_failure_records_failed_states_and_succeeds_on_retry(): void
    {
        $doc = $this->makeDoc();

        $provider = $this->switchableProvider();

        try {
            $this->job($doc)->handle($this->service());
            $this->fail('Expected GeminiProviderException.');
        } catch (GeminiProviderException) {
            // expected — the queue retries
        }

        foreach (['en', 'fr', 'de'] as $locale) {
            $state = $this->states->stateFor($doc, 'title', $locale);
            $this->assertSame(TranslationState::STATUS_FAILED, $state?->status);
            $this->assertStringContainsString('HTTP 500', (string) $state->error);
        }

        $provider->down = false;

        $this->job($doc)->handle($this->service());

        $doc->refresh();
        foreach (['en', 'fr', 'de'] as $locale) {
            $this->assertSame("Privacyverklaring [{$locale}]", $doc->getTranslation('title', $locale, false));
            $this->assertSame(TranslationState::STATUS_MACHINE, $this->states->stateFor($doc, 'title', $locale)?->status);
        }
    }

    // 7. Stale locale with an old value fails, old value kept, retried.
    public function test_stale_locale_that_fails_keeps_old_value_and_is_retried(): void
    {
        $doc = $this->makeDoc();
        foreach (['en' => 'Old en', 'fr' => 'Old fr', 'de' => 'Old de'] as $l => $v) {
            $doc->setTranslation('title', $l, $v);
        }
        $doc->setTranslation('body', 'en', 'Body en');
        $doc->setTranslation('body', 'fr', 'Body fr');
        $doc->setTranslation('body', 'de', 'Body de');
        $doc->save();
        $doc->refresh();
        $this->states->recordState($doc, 'title', 'en', TranslationState::STATUS_STALE, 'nl', 'x', 0);

        $provider = $this->switchableProvider();

        try {
            $this->job($doc)->handle($this->service());
            $this->fail('Expected GeminiProviderException.');
        } catch (GeminiProviderException) {
            // expected
        }

        $doc->refresh();
        $this->assertSame('Old en', $doc->getTranslation('title', 'en', false));
        $this->assertSame(TranslationState::STATUS_FAILED, $this->states->stateFor($doc, 'title', 'en')?->status);

        $provider->down = false;

        $this->job($doc)->handle($this->service());

        $doc->refresh();
        $this->assertSame('Privacyverklaring [en]', $doc->getTranslation('title', 'en', false));
        $this->assertSame(TranslationState::STATUS_MACHINE, $this->states->stateFor($doc, 'title', 'en')?->status);
    }

    // 8. Preservation: generateStructuredResponse never throws.
    public function test_generate_structured_response_still_returns_empty_array_on_http_failure(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->assertSame([], $this->service()->generateStructuredResponse('anything'));
    }

    public function test_generate_structured_response_still_returns_empty_array_without_token(): void
    {
        $auth = \Mockery::mock(GoogleServiceAccountAuthService::class);
        $auth->shouldReceive('getAccessToken')->andReturn(null);

        Http::fake();

        $this->assertSame([], (new GeminiService($auth))->generateStructuredResponse('anything'));
        Http::assertNothingSent();
    }

    // 9. On the sync queue there is no retry: a provider failure must not
    //    break the request that saved the model (FieldOps saves run the job
    //    inline). Failed states are still recorded.
    public function test_job_on_the_sync_queue_records_failed_states_without_rethrowing(): void
    {
        $doc = $this->makeDoc();
        $this->switchableProvider(); // down: HTTP 500

        $job = $this->job($doc);
        $job->setJob(new SyncJob(app(), '{}', 'sync', 'default'));

        $job->handle($this->service());

        foreach (['en', 'fr', 'de'] as $locale) {
            $state = $this->states->stateFor($doc, 'title', $locale);

            $this->assertSame(TranslationState::STATUS_FAILED, $state?->status);
            $this->assertStringContainsString('HTTP 500', (string) $state->error);
        }
    }

    // 10. An HTTP 200 whose body is not a Gemini answer (proxy HTML page) is a
    //     provider failure on both translation paths, not an empty translation.
    public function test_translation_paths_reject_a_non_json_http_200(): void
    {
        Http::fake(['*' => Http::response('<html>bad gateway</html>', 200)]);

        foreach (['single', 'batch'] as $path) {
            try {
                $path === 'single'
                    ? $this->service()->translateAndDetect('Hallo', ['en'])
                    : $this->service()->translateBatch([11 => 'Hallo'], 'en');

                $this->fail("Expected GeminiProviderException on the {$path} path.");
            } catch (GeminiProviderException $e) {
                $this->assertSame('Gemini response had no answer text.', $e->getMessage());
            }
        }
    }

    // 11. Preservation for the shared generic method: the same HTML answer and
    //     a scalar answer text both yield [] (a scalar used to crash with a
    //     TypeError, which no caller handled).
    public function test_generate_structured_response_returns_empty_array_for_unusable_answers(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push('<html>bad gateway</html>', 200)
            ->push($this->wrapText('"hello"'))]);

        $this->assertSame([], $this->service()->generateStructuredResponse('anything'));
        $this->assertSame([], $this->service()->generateStructuredResponse('anything'));
        Http::assertSentCount(2);
    }

    // 12. A successful retry clears the error text of the earlier failure, so
    //     the panel never shows a stale reason on a machine translation.
    public function test_a_successful_retry_clears_the_previous_error(): void
    {
        $doc = $this->makeDoc();
        $provider = $this->switchableProvider();

        try {
            $this->job($doc)->handle($this->service());
        } catch (GeminiProviderException) {
            // first attempt fails and is rethrown for the queue
        }

        $provider->down = false;
        $this->job($doc)->handle($this->service());

        $state = $this->states->stateFor($doc, 'title', 'en');
        $this->assertSame(TranslationState::STATUS_MACHINE, $state?->status);
        $this->assertNull($state->error);
    }

    private function wrapText(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
    }
}
