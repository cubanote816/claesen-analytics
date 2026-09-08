<?php

namespace Modules\Mailing\Tests\Feature;

use App\Contracts\MarketingCampaignInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Services\MicrosoftGraphMailer;
use Modules\Mailing\Services\SuppressionService;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Tests\TestCase;

/**
 * CLA-532: with driver 'microsoft-graph' but no credentials, the send loop must
 * end with the campaign FAILED, zero 'sent' messages, sent_count = 0 and no
 * outbound HTTP — a controlled MailConfigurationException, never a TypeError,
 * never a campaign marked sent.
 *
 * DB-backed (RefreshDatabase). Authored here; executed in CI (no local MySQL).
 */
class GraphUnconfiguredCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mailing.send_delay_ms' => 0]);
        config([
            'app.mailing_driver' => 'microsoft-graph',
            'mail.mailers.microsoft-graph.client_id' => null,
            'mail.mailers.microsoft-graph.tenant_id' => null,
            'mail.mailers.microsoft-graph.client_secret' => null,
        ]);
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_campaign_fails_closed_with_zero_sent_and_no_http(): void
    {
        $this->assertInstanceOf(MicrosoftGraphMailer::class, app(MarketingCampaignInterface::class));

        $region = Region::firstOrCreate(['name' => 'R'], ['slug' => 'r']);
        $p1 = Prospect::create(['name' => 'C1-'.uniqid(), 'region_id' => $region->id, 'unsubscribed_at' => null]);
        $p2 = Prospect::create(['name' => 'C2-'.uniqid(), 'region_id' => $region->id, 'unsubscribed_at' => null]);
        foreach ([$p1, $p2] as $p) {
            ProspectLocation::create(['prospect_id' => $p->id, 'contact_type' => 'main', 'email' => 'p'.$p->id.'@example.com']);
        }

        $tpl = EmailTemplate::create(['name' => 'T', 'subject' => 'Hi {{ name }}', 'body' => '<p>Dear {{ name }}</p>']);
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::APPROVED,
            'template_id' => $tpl->id,
            'subject_snapshot' => $tpl->subject,
            'body_snapshot' => $tpl->body,
        ]);

        (new ExecuteCampaignJob(campaignId: $campaign->id, overrideProspectIds: [$p1->id, $p2->id]))
            ->handle(app(MarketingCampaignInterface::class), app(SuppressionService::class));

        $this->assertDatabaseHas('mailing_campaigns', [
            'id' => $campaign->id,
            'status' => CampaignStatus::FAILED->value,
            'sent_count' => 0,
        ]);
        $this->assertDatabaseMissing('mailing_messages', ['campaign_id' => $campaign->id, 'status' => 'sent']);
        $this->assertSame(
            2,
            $campaign->messages()->where('status', 'failed')->count(),
        );
        Http::assertNothingSent();
    }
}
