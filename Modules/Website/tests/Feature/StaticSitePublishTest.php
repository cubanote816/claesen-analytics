<?php

namespace Modules\Website\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Website\App\Enums\PublicationStatus;
use Modules\Website\DTOs\WebhookResult;
use Modules\Website\Jobs\GenerateGalleryMediaMetadataJob;
use Modules\Website\Jobs\TriggerStaticSiteRebuildJob;
use Modules\Website\Models\Project;
use Modules\Website\Models\PublicationState;
use Modules\Website\Observers\MediaObserver;
use Modules\Website\Observers\ProjectObserver;
use Modules\Website\Services\StaticSitePublicationService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StaticSitePublishTest extends TestCase
{
    use RefreshDatabase;

    private StaticSitePublicationService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'static_site.enabled'                    => true,
            'static_site.webhook_url'                => 'http://fake-frontend:9000/rebuild',
            'static_site.webhook_secret'             => 'test-secret',
            'static_site.webhook_timeout'            => 3,
            'static_site.environment'                => 'testing',
            'static_site.debounce_seconds'           => 20,
            'static_site.signature_tolerance_seconds'=> 300,
        ]);

        $this->svc = new StaticSitePublicationService();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function freshState(string $key): PublicationState
    {
        \DB::table('website_publication_states')->truncate();
        $s = PublicationState::current();
        $s->markPending();
        $s->recordDispatch($key);
        return $s;
    }

    private function createProject(array $overrides = []): Project
    {
        return Project::withoutEvents(fn () => Project::create(array_merge([
            'slug'      => 'test-' . uniqid(),
            'title'     => ['nl' => 'Test NL', 'en' => 'Test EN'],
            'category'  => 'sport',
            'published' => true,
        ], $overrides)));
    }

    private function fakeMedia(string $modelType, string $collection, int $id = 1): Media
    {
        $media = new Media();
        $media->forceFill([
            'id'             => $id,
            'model_type'     => $modelType,
            'collection_name'=> $collection,
        ]);
        return $media;
    }

    // ─── Config guard ─────────────────────────────────────────────────────────

    public function test_request_rebuild_does_nothing_when_disabled(): void
    {
        Queue::fake();
        config(['static_site.enabled' => false]);

        $this->svc->requestRebuild('content_changed');

        Queue::assertNothingPushed();
        // PublicationState row should not even be created
        $this->assertEquals(0, \DB::table('website_publication_states')->count());
    }

    // ─── requestRebuild ───────────────────────────────────────────────────────

    public function test_request_rebuild_marks_state_pending_and_dispatches_job(): void
    {
        Queue::fake();

        $this->svc->requestRebuild('content_changed');

        Queue::assertPushed(TriggerStaticSiteRebuildJob::class);
        $state = PublicationState::current();
        $this->assertSame(PublicationStatus::PENDING, $state->status);
        $this->assertNotNull($state->dispatch_key);
        $this->assertNotNull($state->dispatched_at);
    }

    public function test_request_rebuild_generates_unique_dispatch_keys(): void
    {
        Queue::fake();

        $this->svc->requestRebuild('content_changed');
        $key1 = PublicationState::current()->dispatch_key;

        $this->svc->requestRebuild('content_changed');
        $key2 = PublicationState::current()->dispatch_key;

        $this->assertNotEquals($key1, $key2);
    }

    public function test_request_rebuild_with_force_true_passes_flag_to_job(): void
    {
        Queue::fake();

        $this->svc->requestRebuild('manual', force: true);

        Queue::assertPushed(TriggerStaticSiteRebuildJob::class, fn ($job) => $job->force === true);
    }

    // ─── Debounce ─────────────────────────────────────────────────────────────

    public function test_debounce_stale_job_aborts_when_key_superseded(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('', 202)]);

        $this->svc->requestRebuild('content_changed');
        $staleKey = PublicationState::current()->dispatch_key;

        $this->svc->requestRebuild('content_changed');
        $activeKey = PublicationState::current()->dispatch_key;

        $this->assertNotEquals($staleKey, $activeKey);

        // Stale job aborts — state stays pending (not accepted)
        (new TriggerStaticSiteRebuildJob($staleKey, 'content_changed', false))->handle($this->svc);
        $this->assertSame(PublicationStatus::PENDING, PublicationState::current()->status);

        // Active job succeeds
        (new TriggerStaticSiteRebuildJob($activeKey, 'content_changed', false))->handle($this->svc);
        $this->assertSame(PublicationStatus::ACCEPTED, PublicationState::current()->status);
    }

    // ─── HMAC / sendWebhook ───────────────────────────────────────────────────

    public function test_send_webhook_returns_ok_on_202(): void
    {
        Http::fake(['*' => Http::response('', 202)]);

        $result = $this->svc->sendWebhook('content_changed', false);

        $this->assertTrue($result->success);
        $this->assertSame(202, $result->statusCode);
        $this->assertNull($result->errorMessage);
    }

    public function test_send_webhook_returns_failure_on_non_202(): void
    {
        Http::fake(['*' => Http::response('Bad Gateway', 502)]);

        $result = $this->svc->sendWebhook('content_changed', false);

        $this->assertFalse($result->success);
        $this->assertSame(502, $result->statusCode);
        $this->assertNotNull($result->errorMessage);
    }

    public function test_send_webhook_uses_hmac_sha256_with_timestamp_dot_body(): void
    {
        $captured = null;
        Http::fake(['*' => function ($req) use (&$captured) {
            $captured = $req;
            return Http::response('', 202);
        }]);

        $this->svc->sendWebhook('content_changed', false);

        $ts  = (int) ($captured->header('X-Webhook-Timestamp')[0] ?? 0);
        $sig = $captured->header('X-Webhook-Signature')[0] ?? '';
        $body = $captured->body();

        // Re-compute expected HMAC locally
        $expected = 'sha256=' . hash_hmac('sha256', $ts . '.' . $body, 'test-secret');

        $this->assertTrue($ts > 0, 'timestamp must be set');
        $this->assertStringStartsWith('sha256=', $sig);
        $this->assertSame($expected, $sig, 'HMAC must sign timestamp.body with the configured secret');
    }

    public function test_send_webhook_payload_contains_required_fields(): void
    {
        $captured = null;
        Http::fake(['*' => function ($req) use (&$captured) {
            $captured = $req;
            return Http::response('', 202);
        }]);

        $this->svc->sendWebhook('manual', force: true);

        $payload = json_decode($captured->body(), true);
        $this->assertSame('backend',  $payload['source']);
        $this->assertSame('testing',  $payload['environment']);
        $this->assertSame('manual',   $payload['reason']);
        $this->assertTrue($payload['force']);
    }

    public function test_send_webhook_returns_failure_when_url_not_configured(): void
    {
        config(['static_site.webhook_url' => null]);

        $result = $this->svc->sendWebhook('content_changed', false);

        $this->assertFalse($result->success);
        $this->assertSame(0, $result->statusCode);
    }

    // ─── WebhookResult DTO ────────────────────────────────────────────────────

    public function test_webhook_result_ok_factory(): void
    {
        $r = WebhookResult::ok(202);
        $this->assertTrue($r->success);
        $this->assertSame(202, $r->statusCode);
        $this->assertNull($r->errorMessage);
    }

    public function test_webhook_result_failure_factory(): void
    {
        $r = WebhookResult::failure(503, 'Service Unavailable');
        $this->assertFalse($r->success);
        $this->assertSame(503, $r->statusCode);
        $this->assertSame('Service Unavailable', $r->errorMessage);
    }

    // ─── Job: all 6 handle() + failed() paths ────────────────────────────────

    public function test_job_superseded_key_aborts_silently(): void
    {
        $s = $this->freshState('A');
        (new TriggerStaticSiteRebuildJob('B', 'test', false))->handle($this->svc);
        $s->refresh();
        $this->assertSame(PublicationStatus::PENDING, $s->status);
    }

    public function test_job_matching_key_and_202_marks_accepted(): void
    {
        $s = $this->freshState('C');
        Http::fake(['*' => Http::response('', 202)]);
        (new TriggerStaticSiteRebuildJob('C', 'content_changed', false))->handle($this->svc);
        $s->refresh();
        $this->assertSame(PublicationStatus::ACCEPTED, $s->status);
        $this->assertNotNull($s->last_accepted_at);
        $this->assertNull($s->dispatch_key);
        $this->assertNull($s->pending_since);
    }

    public function test_job_202_but_key_changed_mid_flight_does_not_mark_accepted(): void
    {
        $s = $this->freshState('D');
        Http::fake(['*' => function () use ($s) {
            $s->recordDispatch('E');
            $s->markPending();
            return Http::response('', 202);
        }]);
        (new TriggerStaticSiteRebuildJob('D', 'content_changed', false))->handle($this->svc);
        $s->refresh();
        $this->assertSame(PublicationStatus::PENDING, $s->status);
        $this->assertSame('E', $s->dispatch_key);
    }

    public function test_job_throws_on_non_202_to_trigger_retry(): void
    {
        $this->freshState('F');
        Http::fake(['*' => Http::response('err', 502)]);
        $this->expectException(\RuntimeException::class);
        (new TriggerStaticSiteRebuildJob('F', 'content_changed', false))->handle($this->svc);
    }

    public function test_job_stays_pending_while_retries_in_flight(): void
    {
        $s = $this->freshState('F');
        Http::fake(['*' => Http::response('err', 502)]);
        try {
            (new TriggerStaticSiteRebuildJob('F', 'content_changed', false))->handle($this->svc);
        } catch (\RuntimeException) {}
        $s->refresh();
        $this->assertSame(PublicationStatus::PENDING, $s->status);
    }

    public function test_job_failed_with_matching_key_marks_error(): void
    {
        $s = $this->freshState('F');
        (new TriggerStaticSiteRebuildJob('F', 'content_changed', false))
            ->failed(new \RuntimeException('connection refused'));
        $s->refresh();
        $this->assertSame(PublicationStatus::ERROR, $s->status);
        $this->assertNotNull($s->last_error);
        $this->assertNotNull($s->last_error_at);
    }

    public function test_job_failed_with_superseded_key_does_not_mark_error(): void
    {
        $s = $this->freshState('G');
        $s->recordDispatch('H');
        (new TriggerStaticSiteRebuildJob('G', 'content_changed', false))
            ->failed(new \RuntimeException('timeout'));
        $s->refresh();
        $this->assertSame(PublicationStatus::PENDING, $s->status);
        $this->assertNull($s->last_error);
    }

    // ─── Observers ────────────────────────────────────────────────────────────

    public function test_project_observer_dispatches_rebuild_on_all_five_events(): void
    {
        Queue::fake();
        $observer = app(ProjectObserver::class);
        $project  = $this->createProject();

        foreach (['created', 'updated', 'deleted', 'restored', 'forceDeleted'] as $event) {
            $observer->$event($project);
        }

        Queue::assertPushed(TriggerStaticSiteRebuildJob::class, 5);
    }

    public function test_media_observer_dispatches_gallery_metadata_job_for_gallery_media(): void
    {
        Queue::fake();
        $observer = app(MediaObserver::class);
        $media    = $this->fakeMedia(Project::class, 'gallery', 42);

        $observer->saved($media);

        Queue::assertPushed(GenerateGalleryMediaMetadataJob::class);
        Queue::assertNotPushed(TriggerStaticSiteRebuildJob::class);
    }

    public function test_media_observer_dispatches_rebuild_for_non_gallery_project_media(): void
    {
        Queue::fake();
        $observer = app(MediaObserver::class);

        foreach (['featured_image', 'detail_gallery'] as $collection) {
            $media = $this->fakeMedia(Project::class, $collection);
            $observer->saved($media);
        }

        Queue::assertPushed(TriggerStaticSiteRebuildJob::class, 2);
    }

    public function test_media_observer_ignores_non_project_media(): void
    {
        Queue::fake();
        $observer = app(MediaObserver::class);
        $media    = $this->fakeMedia('App\\Models\\User', 'avatar');

        $observer->saved($media);
        $observer->deleted($media);

        Queue::assertNothingPushed();
    }

    public function test_media_observer_dispatches_rebuild_on_delete_for_any_collection(): void
    {
        Queue::fake();
        $observer = app(MediaObserver::class);

        foreach (['gallery', 'featured_image', 'detail_gallery'] as $collection) {
            $media = $this->fakeMedia(Project::class, $collection);
            $observer->deleted($media);
        }

        Queue::assertPushed(TriggerStaticSiteRebuildJob::class, 3);
    }

    // ─── Manual rebuild button (force=true) ───────────────────────────────────

    public function test_manual_rebuild_dispatches_job_with_force_true(): void
    {
        Queue::fake();

        $this->svc->requestRebuild('manual', force: true);

        Queue::assertPushed(TriggerStaticSiteRebuildJob::class, fn ($job) =>
            $job->force === true && $job->reason === 'manual'
        );
    }
}
