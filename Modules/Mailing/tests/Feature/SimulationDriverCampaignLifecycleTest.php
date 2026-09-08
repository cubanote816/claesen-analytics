<?php

namespace Modules\Mailing\Tests\Feature;

use App\Contracts\MarketingCampaignInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Exceptions\MailConfigurationException;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Services\SimulationMailer;
use Modules\Mailing\Services\SuppressionService;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Tests\TestCase;

/**
 * CLA-532: end-to-end behaviour of the campaign send loop under the explicit
 * 'simulation' driver, and the fail-closed behaviour of an unknown driver.
 *
 * DB-backed (RefreshDatabase). Authored here; executed in CI (no local MySQL).
 */
class SimulationDriverCampaignLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mailing.send_delay_ms' => 0]);
        Http::preventStrayRequests();
        Http::fake();
    }

    private function campaignWithOneProspect(): array
    {
        $region = Region::firstOrCreate(['name' => 'R'], ['slug' => 'r']);
        $p = Prospect::create(['name' => 'C-'.uniqid(), 'region_id' => $region->id, 'unsubscribed_at' => null]);
        ProspectLocation::create(['prospect_id' => $p->id, 'contact_type' => 'main', 'email' => 'p'.$p->id.'@example.com']);

        $tpl = EmailTemplate::create(['name' => 'T', 'subject' => 'Hi {{ name }}', 'body' => '<p>Dear {{ name }}</p>']);
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::APPROVED,
            'template_id' => $tpl->id,
            'subject_snapshot' => $tpl->subject,
            'body_snapshot' => $tpl->body,
        ]);

        return [$campaign, [$p->id]];
    }

    private function run(Campaign $campaign, array $ids): void
    {
        (new ExecuteCampaignJob(campaignId: $campaign->id, overrideProspectIds: $ids))
            ->handle(app(MarketingCampaignInterface::class), app(SuppressionService::class));
    }

    public function test_simulation_completes_the_campaign_and_marks_messages_sent_without_http(): void
    {
        config(['app.mailing_driver' => 'simulation']);
        $this->assertInstanceOf(SimulationMailer::class, app(MarketingCampaignInterface::class));

        [$campaign, $ids] = $this->campaignWithOneProspect();
        $this->run($campaign, $ids);

        $this->assertDatabaseHas('mailing_campaigns', [
            'id' => $campaign->id,
            'status' => CampaignStatus::COMPLETED->value,
            'sent_count' => 1,
            'failed_count' => 0,
        ]);
        $this->assertDatabaseHas('mailing_messages', ['campaign_id' => $campaign->id, 'status' => 'sent']);
        $this->assertDatabaseMissing('mailing_messages', ['campaign_id' => $campaign->id, 'status' => 'failed']);
        Http::assertNothingSent();
    }

    public function test_simulation_in_production_fails_the_campaign_with_zero_sent(): void
    {
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            config(['app.mailing_driver' => 'simulation']);

            [$campaign, $ids] = $this->campaignWithOneProspect();
            $this->run($campaign, $ids);
        } finally {
            $this->app['env'] = $originalEnv;
        }

        $this->assertDatabaseHas('mailing_campaigns', [
            'id' => $campaign->id,
            'status' => CampaignStatus::FAILED->value,
            'sent_count' => 0,
        ]);
        $this->assertDatabaseHas('mailing_messages', ['campaign_id' => $campaign->id, 'status' => 'failed']);
        $this->assertDatabaseMissing('mailing_messages', ['campaign_id' => $campaign->id, 'status' => 'sent']);
        Http::assertNothingSent();
    }

    public function test_unknown_driver_raises_before_any_message_is_written_and_sends_nothing(): void
    {
        config(['app.mailing_driver' => 'grph']);

        [$campaign, $ids] = $this->campaignWithOneProspect();

        try {
            $this->run($campaign, $ids);
            $this->fail('Expected MailConfigurationException.');
        } catch (MailConfigurationException $e) {
            $this->assertStringContainsString('Unknown mailing driver', $e->getMessage());
        }

        $this->assertDatabaseCount('mailing_messages', 0);
        $this->assertDatabaseMissing('mailing_campaigns', [
            'id' => $campaign->id,
            'status' => CampaignStatus::COMPLETED->value,
        ]);
        Http::assertNothingSent();
    }
}
