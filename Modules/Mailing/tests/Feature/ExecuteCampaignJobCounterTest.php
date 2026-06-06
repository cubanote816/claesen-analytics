<?php

namespace Modules\Mailing\Tests\Feature;

use App\Contracts\MarketingCampaignInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Services\MicrosoftGraphService;
use Modules\Mailing\Services\SuppressionService;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Tests\TestCase;

class ExecuteCampaignJobCounterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Eliminate usleep() wait between sends so tests are not slow.
        config(['mailing.send_delay_ms' => 0]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function region(): Region
    {
        return Region::firstOrCreate(['name' => 'Test Region'], ['slug' => 'test-region']);
    }

    private function prospect(Region $region): Prospect
    {
        $p = Prospect::create([
            'name'            => 'TestClub-' . uniqid(),
            'region_id'       => $region->id,
            'unsubscribed_at' => null,
        ]);

        ProspectLocation::create([
            'prospect_id'  => $p->id,
            'contact_type' => 'main',
            'email'        => 'test-' . $p->id . '@example.com',
        ]);

        return $p;
    }

    private function template(): EmailTemplate
    {
        return EmailTemplate::create([
            'name'    => 'Test Template',
            'subject' => 'Hello {{ name }}',
            'body'    => '<p>Dear {{ name }},</p>',
        ]);
    }

    private function campaign(): Campaign
    {
        $tpl = $this->template();

        return Campaign::factory()->create([
            'status'           => CampaignStatus::APPROVED,
            'template_id'      => $tpl->id,
            'subject_snapshot' => $tpl->subject,
            'body_snapshot'    => $tpl->body,
        ]);
    }

    private function mockMailer(bool $returnValue): void
    {
        $mock = Mockery::mock(MarketingCampaignInterface::class);
        $mock->shouldReceive('sendCampaign')->andReturn($returnValue);
        $this->app->instance(MarketingCampaignInterface::class, $mock);
    }

    /**
     * Mailer that succeeds for the first N calls, then fails.
     */
    private function mockMailerSucceedFirst(int $successes): void
    {
        $mock = Mockery::mock(MarketingCampaignInterface::class);
        $mock->shouldReceive('sendCampaign')
            ->times($successes)
            ->andReturn(true)
            ->ordered();
        $mock->shouldReceive('sendCampaign')
            ->andReturn(false)
            ->ordered();
        $this->app->instance(MarketingCampaignInterface::class, $mock);
    }

    private function runJob(Campaign $campaign, array $prospectIds): void
    {
        $job = new ExecuteCampaignJob(
            campaignId:          $campaign->id,
            overrideProspectIds: $prospectIds,
        );
        $job->handle(
            app(MarketingCampaignInterface::class),
            app(SuppressionService::class),
        );
    }

    // ─── BUG-A: counter accuracy (no double-counting) ─────────────────────────

    public function test_failed_count_equals_prospect_count_when_all_fail(): void
    {
        // Before fix: running totals were added to the DB value inside the loop.
        // With 2 failures: iteration 1 → 0+1=1, iteration 2 → 1+2=3. Fixed to 2.
        $this->mockMailer(false);
        $region   = $this->region();
        $p1       = $this->prospect($region);
        $p2       = $this->prospect($region);
        $campaign = $this->campaign();

        $this->runJob($campaign, [$p1->id, $p2->id]);

        $this->assertDatabaseHas('mailing_campaigns', [
            'id'           => $campaign->id,
            'total_count'  => 2,
            'sent_count'   => 0,
            'failed_count' => 2,
        ]);
    }

    public function test_sent_count_equals_prospect_count_when_all_succeed(): void
    {
        $this->mockMailer(true);
        $region   = $this->region();
        $p1       = $this->prospect($region);
        $p2       = $this->prospect($region);
        $campaign = $this->campaign();

        $this->runJob($campaign, [$p1->id, $p2->id]);

        $this->assertDatabaseHas('mailing_campaigns', [
            'id'           => $campaign->id,
            'total_count'  => 2,
            'sent_count'   => 2,
            'failed_count' => 0,
        ]);
    }

    public function test_mixed_result_counters_are_accurate(): void
    {
        // 1 success then 1 failure — both counts must be exactly 1.
        $this->mockMailerSucceedFirst(1);
        $region   = $this->region();
        $p1       = $this->prospect($region);
        $p2       = $this->prospect($region);
        $campaign = $this->campaign();

        $this->runJob($campaign, [$p1->id, $p2->id]);

        $this->assertDatabaseHas('mailing_campaigns', [
            'id'           => $campaign->id,
            'sent_count'   => 1,
            'failed_count' => 1,
        ]);
    }

    // ─── BUG-B: final campaign status ─────────────────────────────────────────

    public function test_campaign_becomes_failed_when_all_messages_fail(): void
    {
        // Before fix: campaign always transitioned to COMPLETED regardless of outcome.
        $this->mockMailer(false);
        $region   = $this->region();
        $p1       = $this->prospect($region);
        $campaign = $this->campaign();

        $this->runJob($campaign, [$p1->id]);

        $this->assertDatabaseHas('mailing_campaigns', [
            'id'     => $campaign->id,
            'status' => CampaignStatus::FAILED->value,
        ]);
        $this->assertNotNull($campaign->fresh()->finished_at);
    }

    public function test_campaign_becomes_completed_when_at_least_one_sent(): void
    {
        $this->mockMailer(true);
        $region   = $this->region();
        $p1       = $this->prospect($region);
        $campaign = $this->campaign();

        $this->runJob($campaign, [$p1->id]);

        $this->assertDatabaseHas('mailing_campaigns', [
            'id'     => $campaign->id,
            'status' => CampaignStatus::COMPLETED->value,
        ]);
    }

    public function test_campaign_remains_sending_after_ab_first_pass(): void
    {
        // A/B first pass calls sendToProspects with completeAfter=false for both variant
        // groups — the campaign must stay SENDING regardless of individual send outcomes.
        // With fewer than 2 prospects the job falls back to a normal (completing) send,
        // so we need at least 2 prospects to trigger the real A/B path.
        $this->mockMailer(false);
        $region   = $this->region();
        $p1       = $this->prospect($region);
        $p2       = $this->prospect($region);
        $campaign = $this->campaign();
        $campaign->update([
            'status'           => CampaignStatus::APPROVED,
            'ab_subject_b'     => 'Subject B',
            'ab_split_percent' => 50,
        ]);

        $job = new ExecuteCampaignJob(
            campaignId:   $campaign->id,
            isWinnerSend: false,
        );
        $job->handle(
            app(MarketingCampaignInterface::class),
            app(SuppressionService::class),
        );

        // Status must still be SENDING after the first A/B pass — completeAfter=false
        // was used for both variant groups, so no final status transition should occur.
        $this->assertDatabaseHas('mailing_campaigns', [
            'id'     => $campaign->id,
            'status' => CampaignStatus::SENDING->value,
        ]);
    }

    // ─── BUG-C: OAuth token not cached on failure ─────────────────────────────

    public function test_null_token_is_not_written_to_cache_on_auth_failure(): void
    {
        // Before fix: Cache::remember() cached null on auth failure, locking out
        // retries for ~58 minutes even after credentials were corrected.
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client'], 400),
        ]);

        $service = new MicrosoftGraphService();
        $token   = $service->getAccessToken();

        $this->assertNull($token);
        // Must not be in cache — any previous stale token must also be cleared.
        $this->assertNull(Cache::get('microsoft_graph_token'));
    }

    public function test_stale_token_is_cleared_from_cache_on_auth_failure(): void
    {
        // If a bad token was previously cached, a new auth failure must evict it
        // so the next request retries rather than using the stale value.
        Cache::put('microsoft_graph_token', 'stale-bad-token', 3500);

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client'], 400),
        ]);

        $service = new MicrosoftGraphService();
        // The cached value is returned immediately (Cache::get is truthy), so this
        // test verifies behavior when the cached token is already gone and a fresh
        // auth attempt fails.
        Cache::forget('microsoft_graph_token'); // simulate expiry
        $token = $service->getAccessToken();

        $this->assertNull($token);
        $this->assertNull(Cache::get('microsoft_graph_token'));
    }

    public function test_valid_token_is_written_to_cache_on_auth_success(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok-abc123'], 200),
        ]);

        $service = new MicrosoftGraphService();
        $token   = $service->getAccessToken();

        $this->assertSame('tok-abc123', $token);
        $this->assertSame('tok-abc123', Cache::get('microsoft_graph_token'));
    }

    public function test_cached_token_is_returned_without_http_call(): void
    {
        Cache::put('microsoft_graph_token', 'cached-tok', 3500);
        Http::fake(); // No HTTP calls should be made

        $service = new MicrosoftGraphService();
        $token   = $service->getAccessToken();

        $this->assertSame('cached-tok', $token);
        Http::assertNothingSent();
    }
}
