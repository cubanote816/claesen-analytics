<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Models\Site;
use Modules\Core\Services\OrganizationContext;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Website\App\Enums\PublicationStatus;
use Modules\Website\Jobs\TriggerStaticSiteRebuildJob;
use Modules\Website\Models\Project;
use Modules\Website\Models\PublicationState;
use Modules\Website\Observers\ProjectObserver;
use Modules\Website\Services\StaticSitePublicationService;
use Tests\TestCase;

/**
 * F3/CLA-472 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * PublicationState was already one row per site (CLA-548) but every caller
 * omitted $siteId, so it was still, in practice, a single global publication
 * pipeline — one dispatch_key, one webhook target (config/static_site.php),
 * one failure domain. This proves the three acceptance criteria that
 * actually needed behaviour, not just a column: independent per-site webhook
 * config, independent dispatch/debounce state, and "a failure on one site
 * never touches another's state".
 */
final class StaticSitePublishPerSiteTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'static_site.enabled' => true,
            'static_site.webhook_url' => 'http://claesen-frontend:9000/rebuild',
            'static_site.webhook_secret' => 'claesen-secret',
            'static_site.webhook_timeout' => 3,
            'static_site.debounce_seconds' => 0,
        ]);
    }

    public function test_claesen_keeps_using_the_global_config_with_no_per_site_override(): void
    {
        Http::fake(['claesen-frontend:9000/*' => Http::response('', 202)]);

        app(StaticSitePublicationService::class)->sendWebhook(Site::claesenId(), 'content_changed', false);

        Http::assertSent(fn ($request) => $request->url() === 'http://claesen-frontend:9000/rebuild');
    }

    public function test_a_site_with_its_own_webhook_config_uses_it_instead_of_the_global_config(): void
    {
        $bertelsSite = $this->bertelsFixtureSite(null, [
            'static_site_webhook_url' => 'http://bertels-frontend:9100/rebuild',
            'static_site_webhook_secret' => 'bertels-secret',
        ]);

        Http::fake([
            'bertels-frontend:9100/*' => Http::response('', 202),
            'claesen-frontend:9000/*' => Http::response('', 500), // must never be hit
        ]);

        $result = app(StaticSitePublicationService::class)->sendWebhook($bertelsSite->id, 'content_changed', false);

        $this->assertTrue($result->success);
        Http::assertSent(fn ($request) => $request->url() === 'http://bertels-frontend:9100/rebuild');
        Http::assertNotSent(fn ($request) => $request->url() === 'http://claesen-frontend:9000/rebuild');
    }

    public function test_the_hmac_signature_is_computed_with_each_sites_own_secret(): void
    {
        $bertelsSite = $this->bertelsFixtureSite(null, [
            'static_site_webhook_url' => 'http://bertels-frontend:9100/rebuild',
            'static_site_webhook_secret' => 'bertels-secret',
        ]);

        Http::fake(['bertels-frontend:9100/*' => Http::response('', 202)]);

        app(StaticSitePublicationService::class)->sendWebhook($bertelsSite->id, 'content_changed', false);

        Http::assertSent(function ($request) {
            $timestamp = $request->header('X-Webhook-Timestamp')[0];
            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), 'bertels-secret');

            return $request->header('X-Webhook-Signature')[0] === $expected;
        });
    }

    public function test_two_sites_get_independent_dispatch_keys_and_publication_state(): void
    {
        Queue::fake();

        $bertelsSite = $this->bertelsFixtureSite();
        $service = app(StaticSitePublicationService::class);

        $service->requestRebuild(Site::claesenId(), 'content_changed');
        $service->requestRebuild($bertelsSite->id, 'content_changed');

        $claesenState = PublicationState::current(Site::claesenId());
        $bertelsState = PublicationState::current($bertelsSite->id);

        $this->assertNotSame($claesenState->dispatch_key, $bertelsState->dispatch_key);
        $this->assertSame(PublicationStatus::PENDING, $claesenState->status);
        $this->assertSame(PublicationStatus::PENDING, $bertelsState->status);

        Queue::assertPushed(
            TriggerStaticSiteRebuildJob::class,
            fn (TriggerStaticSiteRebuildJob $job) => $job->siteId === Site::claesenId()
        );
        Queue::assertPushed(
            TriggerStaticSiteRebuildJob::class,
            fn (TriggerStaticSiteRebuildJob $job) => $job->siteId === $bertelsSite->id
        );
    }

    public function test_a_failed_webhook_for_one_site_never_marks_error_on_another_sites_state(): void
    {
        $bertelsSite = $this->bertelsFixtureSite(null, [
            'static_site_webhook_url' => 'http://bertels-frontend:9100/rebuild',
            'static_site_webhook_secret' => 'bertels-secret',
        ]);

        Http::fake([
            'bertels-frontend:9100/*' => Http::response('', 500),
            'claesen-frontend:9000/*' => Http::response('', 202),
        ]);

        $service = app(StaticSitePublicationService::class);
        $claesenState = PublicationState::current(Site::claesenId());
        $claesenState->markPending();
        $claesenState->recordDispatch('claesen-key');

        $bertelsState = PublicationState::current($bertelsSite->id);
        $bertelsState->markPending();
        $bertelsState->recordDispatch('bertels-key');

        (new TriggerStaticSiteRebuildJob($bertelsSite->id, 'bertels-key', 'content_changed', false))
            ->failed(new \RuntimeException('Bertels webhook exhausted retries'));

        $this->assertSame(PublicationStatus::ERROR, $bertelsState->fresh()->status);
        // Claesen's own state is untouched — a different site's failure
        // never reaches it.
        $this->assertSame(PublicationStatus::PENDING, $claesenState->fresh()->status);
        $this->assertNull($claesenState->fresh()->last_error);
    }

    public function test_the_job_resolves_the_site_into_context_so_a_fresh_worker_process_can_run_it(): void
    {
        // Simulates the real worker scenario D6 describes: no HTTP request,
        // no authenticated user — OrganizationContext starts with nothing
        // resolved, exactly like a fresh queue worker process picking this
        // job up. Only the job's own constructor payload (D6: "el job carga
        // el organizationId/siteId como escalar") should be needed.
        $bertelsSite = $this->bertelsFixtureSite(null, [
            'static_site_webhook_url' => 'http://bertels-frontend:9100/rebuild',
            'static_site_webhook_secret' => 'bertels-secret',
        ]);
        $this->enableOrganizationEnforcement();

        Http::fake(['bertels-frontend:9100/*' => Http::response('', 202)]);

        $context = app(OrganizationContext::class);
        $this->assertNull($context->siteId());

        $state = PublicationState::current($bertelsSite->id);
        $state->markPending();
        $state->recordDispatch('fresh-worker-key');

        (new TriggerStaticSiteRebuildJob($bertelsSite->id, 'fresh-worker-key', 'content_changed', false))
            ->handle(app(StaticSitePublicationService::class));

        $this->assertSame(PublicationStatus::ACCEPTED, $state->fresh()->status);
    }

    public function test_saving_a_project_requests_a_rebuild_for_its_own_site_not_claesens(): void
    {
        Queue::fake();

        $bertelsSite = $this->bertelsFixtureSite();
        $project = Project::factory()->create(['site_id' => $bertelsSite->id]);

        (new ProjectObserver(app(StaticSitePublicationService::class)))->updated($project);

        Queue::assertPushed(
            TriggerStaticSiteRebuildJob::class,
            fn (TriggerStaticSiteRebuildJob $job) => $job->siteId === $bertelsSite->id
        );
        Queue::assertNotPushed(
            TriggerStaticSiteRebuildJob::class,
            fn (TriggerStaticSiteRebuildJob $job) => $job->siteId === Site::claesenId()
        );
    }
}
