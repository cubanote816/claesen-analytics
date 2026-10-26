<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Models\Site;
use Modules\Website\App\Enums\PublicationStatus;
use Modules\Website\Jobs\TriggerStaticSiteRebuildJob;
use Modules\Website\Models\Project;
use Modules\Website\Models\PublicationState;
use Modules\Website\Models\SiteSetting;
use Modules\Website\Services\StaticSitePublicationService;
use Tests\TestCase;

/**
 * The deliberate-publishing model, decided by the user on 2026-10-06.
 *
 * Until now, saving content published it: the Project and Media observers, and the gallery
 * metadata job, called requestRebuild() themselves, so an edit was live 20 seconds later.
 * That makes "I'll publish when I'm ready" impossible to express, which is the whole point
 * of the client-facing publish flow.
 *
 * What changes: saving marks the site dirty and builds nothing. The explicit action —
 * requestRebuild(), which the "publish now" button calls — stays the only trigger.
 *
 * What deliberately does NOT change: saving must still mark it dirty. If an edit left no
 * trace, the client would lose the only signal that something is waiting to be published,
 * and "editing" versus "ready" would collapse back into one undifferentiated state.
 */
final class DeliberatePublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'static_site.enabled'          => true,
            'static_site.webhook_url'      => 'http://fake-frontend:9000/rebuild',
            'static_site.webhook_secret'   => 'test-secret',
            'static_site.debounce_seconds' => 20,
        ]);

        Queue::fake();
    }

    private function state(): PublicationState
    {
        return PublicationState::current(Site::claesenId())->fresh();
    }

    public function test_saving_a_project_marks_the_site_dirty_without_rebuilding(): void
    {
        Project::factory()->create(['site_id' => Site::claesenId()]);

        $this->assertSame(PublicationStatus::PENDING, $this->state()->status);
        $this->assertNotNull($this->state()->pending_since);
        Queue::assertNotPushed(TriggerStaticSiteRebuildJob::class);
    }

    /**
     * The gap the analysis found: SiteSetting had no observer at all, so editing the site
     * settings left no trace anywhere. Under deliberate publishing that matters more than
     * it did before, because the trace IS the feature.
     */
    public function test_saving_a_site_setting_marks_the_site_dirty_without_rebuilding(): void
    {
        SiteSetting::factory()->create([
            'key'   => 'email',
            'type'  => SiteSetting::TYPE_TEXT,
            'value' => 'info@electrobertels.be',
        ]);

        $this->assertSame(PublicationStatus::PENDING, $this->state()->status);
        Queue::assertNotPushed(TriggerStaticSiteRebuildJob::class);
    }

    /**
     * The half that must survive the change: the explicit action still publishes.
     */
    public function test_publishing_explicitly_still_dispatches_a_forced_rebuild(): void
    {
        app(StaticSitePublicationService::class)->requestRebuild(
            Site::claesenId(),
            'manual',
            force: true,
        );

        Queue::assertPushed(
            TriggerStaticSiteRebuildJob::class,
            fn (TriggerStaticSiteRebuildJob $job): bool => $job->force === true,
        );
    }

    /**
     * With the pipeline switched off nothing can ever publish, so a "pending" badge would
     * be one nobody can clear. Marking is gated exactly like rebuilding is.
     *
     * Asserted against the table rather than through PublicationState::current(), which
     * would create the idle row itself and make the assertion vacuous.
     */
    public function test_marking_content_changed_writes_nothing_when_the_pipeline_is_off(): void
    {
        config(['static_site.enabled' => false]);

        app(StaticSitePublicationService::class)->markContentChanged(Site::claesenId());

        $this->assertDatabaseCount('website_publication_states', 0);
    }
}
