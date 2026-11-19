<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Models\Site;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Jobs\SendConsultationEmailJob;
use Modules\Website\Models\ConsultationEmailDelivery;
use Modules\Website\Models\ConsultationRequest;
use Tests\TestCase;

/**
 * CLA-705: the landing's proxy names the site (`?site=electro-bertels`).
 * ResolveRequestSite falls back to Claesen when that site is not active, so
 * without this guard a Bertels lead would land in Claesen's inbox. A named
 * site that does not resolve fails closed (503) before anything is stored,
 * queued or replayed; a request without `?site=` is untouched.
 */
final class IntakeSiteGuardTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => []]);
        });

        Http::fake();
    }

    public function test_an_active_named_site_stores_the_lead_under_that_site(): void
    {
        Queue::fake();
        $site = $this->bertelsFixtureSite();

        $this->postJson('/v1/website/consultations?site='.$site->key, $this->payload())
            ->assertCreated();

        $this->assertSame([$site->id], $this->leads()->pluck('site_id')->all());
        Queue::assertPushed(SendConsultationEmailJob::class);
    }

    public function test_a_suspended_named_site_is_rejected_without_storing_or_queueing(): void
    {
        Queue::fake();
        $site = $this->bertelsFixtureSite();
        $site->update(['status' => Site::STATUS_SUSPENDED]);
        // Relative: DatabaseTruncation suites earlier in the run commit their rows.
        $deliveries = ConsultationEmailDelivery::query()->withoutGlobalScopes()->count();

        $this->postJson('/v1/website/consultations?site='.$site->key, $this->payload())
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Intake temporarily unavailable.']);

        $this->assertSame(0, $this->leads()->count());
        $this->assertSame($deliveries, ConsultationEmailDelivery::query()->withoutGlobalScopes()->count());
        Queue::assertNothingPushed();
    }

    public function test_an_unknown_named_site_is_rejected_and_its_value_is_never_logged(): void
    {
        Log::spy();

        $this->postJson('/v1/website/consultations?site=evil-site-<x>', $this->payload())
            ->assertStatus(503);

        $this->assertSame(0, $this->leads()->count());
        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context = []): bool => ! str_contains($message.json_encode($context), 'evil-site')
        )->once();
    }

    public function test_a_replayed_idempotency_key_is_rejected_once_the_site_is_suspended(): void
    {
        $site = $this->bertelsFixtureSite();
        $headers = ['Idempotency-Key' => 'cla-705-replay'];

        $this->postJson('/v1/website/consultations?site='.$site->key, $this->payload(), $headers)
            ->assertCreated();
        $deliveries = ConsultationEmailDelivery::query()->withoutGlobalScopes()->count();

        $site->update(['status' => Site::STATUS_SUSPENDED]);

        $this->postJson('/v1/website/consultations?site='.$site->key, $this->payload(), $headers)
            ->assertStatus(503);

        $this->assertSame(1, $this->leads()->count());
        $this->assertSame($deliveries, ConsultationEmailDelivery::query()->withoutGlobalScopes()->count());
    }

    public function test_a_present_but_blank_site_is_rejected_instead_of_falling_back_to_claesen(): void
    {
        foreach (['site=', 'site=%20'] as $query) {
            $this->postJson('/v1/website/consultations?'.$query, $this->payload())
                ->assertStatus(503);
        }

        $this->assertSame(0, $this->leads()->count());
    }

    public function test_an_ambiguous_repeated_site_selector_is_rejected(): void
    {
        Queue::fake();
        $site = $this->bertelsFixtureSite();

        foreach (['site=unknown&site='.$site->key, 'site='.$site->key.'&site='.$site->key] as $query) {
            $this->postJson('/v1/website/consultations?'.$query, $this->payload())
                ->assertStatus(503);
        }

        $this->assertSame(0, $this->leads()->count());
        Queue::assertNothingPushed();
    }

    public function test_a_suspended_claesen_site_named_explicitly_is_rejected(): void
    {
        Site::query()->whereKey(Site::claesenId())->update(['status' => Site::STATUS_SUSPENDED]);

        $this->postJson('/v1/website/consultations?site='.Site::CLAESEN_KEY, $this->payload())
            ->assertStatus(503);

        $this->assertSame(0, $this->leads()->count());
    }

    public function test_a_request_without_a_named_site_keeps_the_claesen_flow(): void
    {
        $this->postJson('/v1/website/consultations', $this->payload())
            ->assertCreated();

        $this->assertSame([Site::claesenId()], $this->leads()->pluck('site_id')->all());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Guard Visitor',
            'email' => 'guard-visitor@example.test',
            'message' => 'Graag meer info.',
        ], $overrides);
    }

    private function leads()
    {
        return ConsultationRequest::query()->withoutGlobalScopes()->where('email', 'guard-visitor@example.test');
    }
}
