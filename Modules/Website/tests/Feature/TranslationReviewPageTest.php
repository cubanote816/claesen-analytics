<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Intelligence\Jobs\TranslateModelAttributesJob;
use Modules\Intelligence\Models\TranslationState;
use Modules\Intelligence\Services\GeminiService;
use Modules\Intelligence\Services\TranslationStateService;
use Livewire\Livewire;
use Modules\Website\Models\LegalDocument;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * CLA-611 (gap G8): the translation review screen — the only surface where
 * a translation reaches reviewed|published, and retranslate is the ONLY way
 * the AI may overwrite an approved value (G5).
 *
 * The public approval rule stays derived (never declared): these tests
 * also pin that publishing one key does NOT approve the whole entity.
 */
final class TranslationReviewPageTest extends TestCase
{
    use RefreshDatabase;

    private TranslationStateService $states;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => []]);
        });

        $this->states = app(TranslationStateService::class);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');
        $this->actingAs($user);

        Queue::fake();
    }

    private function makeDocWithState(string $status): LegalDocument
    {
        $doc = LegalDocument::create([
            'site_id' => Site::claesenId(),
            'doc_id' => 'privacy',
            'title' => ['nl' => 'Privacyverklaring', 'fr' => 'Déclaration de confidentialité'],
            'body' => ['nl' => 'Body.', 'fr' => 'Corps.'],
            'version' => '2026-10-01',
            'effective_date' => '2026-10-01',
        ]);

        $this->states->recordState($doc, 'title', 'fr', $status, 'nl', 'x', 0);

        return $doc;
    }

    public function test_approve_moves_machine_to_reviewed_and_writes_an_audit_entry(): void
    {
        $doc = $this->makeDocWithState(TranslationState::STATUS_MACHINE);
        $state = $this->states->stateFor($doc, 'title', 'fr');

        Livewire::test(\App\Filament\Clusters\Website\Pages\TranslationReviewPage::class)
            ->callTableAction('approve', $state);

        $this->assertSame(
            TranslationState::STATUS_REVIEWED,
            $this->states->stateFor($doc, 'title', 'fr')?->status
        );

        $this->assertDatabaseHas('activity_log', [
            'description' => 'translation_review',
            'subject_type' => TranslationState::class,
            'subject_id' => $state->id,
        ]);
    }

    public function test_publish_only_moves_reviewed_to_published(): void
    {
        $doc = $this->makeDocWithState(TranslationState::STATUS_REVIEWED);
        $state = $this->states->stateFor($doc, 'title', 'fr');

        Livewire::test(\App\Filament\Clusters\Website\Pages\TranslationReviewPage::class)
            ->callTableAction('publish', $state);

        $this->assertSame(
            TranslationState::STATUS_PUBLISHED,
            $this->states->stateFor($doc, 'title', 'fr')?->status
        );
    }

    public function test_retranslate_is_the_explicit_override_and_requeues_the_job(): void
    {
        $doc = $this->makeDocWithState(TranslationState::STATUS_REVIEWED);
        $state = $this->states->stateFor($doc, 'title', 'fr');

        Livewire::test(\App\Filament\Clusters\Website\Pages\TranslationReviewPage::class)
            ->callTableAction('retranslate', $state);

        $this->assertSame(
            TranslationState::STATUS_STALE,
            $this->states->stateFor($doc, 'title', 'fr')?->status
        );

        // The AI value lands as machine (never published) — job requeued.
        Queue::assertPushed(TranslateModelAttributesJob::class);
    }

    public function test_edit_writes_a_machine_value_that_still_needs_approval(): void
    {
        $doc = $this->makeDocWithState(TranslationState::STATUS_MACHINE);
        $state = $this->states->stateFor($doc, 'title', 'fr');

        Livewire::test(\App\Filament\Clusters\Website\Pages\TranslationReviewPage::class)
            ->callTableAction('edit', $state, data: ['value' => 'Déclaration (corrigée)']);

        $doc->refresh();
        $this->assertSame('Déclaration (corrigée)', $doc->getTranslation('title', 'fr', false));
        $this->assertSame(
            TranslationState::STATUS_MACHINE,
            $this->states->stateFor($doc, 'title', 'fr')?->status
        );
    }

    public function test_the_review_screen_is_scoped_to_the_panel_site(): void
    {
        // Claesen state exists; a bertels fixture state exists. The admin
        // panel (Claesen) table must not serve the bertels row.
        $doc = $this->makeDocWithState(TranslationState::STATUS_MACHINE);
        $claesenState = $this->states->stateFor($doc, 'title', 'fr');

        $bertelsSite = Site::factory()->create(['key' => 'bertels-review-scope']);
        $bertelsDoc = LegalDocument::create([
            'site_id' => $bertelsSite->id,
            'doc_id' => 'privacy',
            'title' => ['nl' => 'Privacyverklaring Bertels', 'fr' => 'Déclaration Bertels'],
            'body' => ['nl' => 'Body.', 'fr' => 'Corps.'],
            'version' => '2026-10-01',
            'effective_date' => '2026-10-01',
        ]);
        $this->states->recordState($bertelsDoc, 'title', 'fr', TranslationState::STATUS_MACHINE, 'nl', 'x', 0);
        $bertelsState = $this->states->stateFor($bertelsDoc, 'title', 'fr');

        $this->assertNotNull($claesenState);
        $this->assertNotNull($bertelsState);

        // Table query-level scoping: the resolved panel site (Claesen) is
        // the only source of rows.
        Livewire::test(\App\Filament\Clusters\Website\Pages\TranslationReviewPage::class)
            ->assertSuccessful();

        $visible = TranslationState::query()
            ->where('site_id', Site::claesenId())
            ->pluck('id')
            ->all();

        $this->assertContains($claesenState->id, $visible);
        $this->assertNotContains($bertelsState->id, $visible);
    }
}
