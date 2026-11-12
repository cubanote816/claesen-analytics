<?php

namespace Modules\Mailing\Tests\Feature;

use App\Contracts\MarketingCampaignInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Modules\Mailing\Enums\AudienceType;
use Modules\Mailing\Enums\TemplateCategory;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Services\SuppressionService;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Tests\TestCase;

class InlineCampaignAudienceTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $sentTo = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['mailing.send_delay_ms' => 0]);

        $mock = Mockery::mock(MarketingCampaignInterface::class);
        $mock->shouldReceive('sendCampaign')->andReturnUsing(function ($prospect, $emails) {
            $this->sentTo[] = $emails[0];

            return true;
        });
        $this->app->instance(MarketingCampaignInterface::class, $mock);
    }

    private function prospect(string $email, bool $unsubscribed = false): Prospect
    {
        $region = Region::firstOrCreate(['name' => 'Test Region'], ['slug' => 'test-region']);
        $p      = Prospect::create(['name' => 'Club-' . uniqid(), 'region_id' => $region->id]);
        ProspectLocation::create(['prospect_id' => $p->id, 'contact_type' => 'main', 'email' => $email]);

        if ($unsubscribed) {
            $p->update(['unsubscribed_at' => now()]);
        }

        return $p;
    }

    private function inlineJob(array $prospectIds): ExecuteCampaignJob
    {
        $template = EmailTemplate::firstOrCreate(['name' => 'Audience Template'], [
            'subject'  => 'Hello {{ name }}',
            'body'     => '<p>Dear {{ name }}</p>',
            'category' => TemplateCategory::TRANSACTIONAL,
        ]);

        return new ExecuteCampaignJob(prospectIds: $prospectIds, templateId: $template->id);
    }

    private function handle(ExecuteCampaignJob $job): void
    {
        $job->handle(app(MarketingCampaignInterface::class), app(SuppressionService::class));
    }

    /** @return array{0: Prospect, 1: Prospect, 2: Prospect} */
    private function threeProspects(): array
    {
        return [
            $this->prospect('a@example.com'),
            $this->prospect('u@example.com', unsubscribed: true),
            $this->prospect('c@example.com'),
        ];
    }

    public function test_inline_campaign_is_stored_as_manual_audience_with_the_dispatched_ids(): void
    {
        config(['mailing.run_budget_seconds' => 3600]);
        [$a, $u, $c] = $this->threeProspects();

        $this->handle($this->inlineJob([$a->id, $u->id, $c->id]));

        $campaign = Campaign::sole();
        $this->assertSame(AudienceType::MANUAL, $campaign->audience_type);
        $this->assertSame(['prospect_ids' => [$a->id, $u->id, $c->id]], $campaign->audience_filters);
    }

    public function test_current_audience_is_the_subscribed_subset_not_the_whole_base(): void
    {
        config(['mailing.run_budget_seconds' => 3600]);
        [$a, $u, $c] = $this->threeProspects();
        $this->prospect('other@example.com');

        $this->handle($this->inlineJob([$a->id, $u->id, $c->id]));

        $audience = Campaign::sole()->resolveAudience();
        $this->assertCount(2, $audience);
        $this->assertEqualsCanonicalizing([$a->id, $c->id], array_map('intval', $audience));
    }

    public function test_sending_is_unchanged_for_the_dispatched_prospects(): void
    {
        config(['mailing.run_budget_seconds' => 3600]);
        [$a, $u, $c] = $this->threeProspects();
        $this->prospect('other@example.com');

        $this->handle($this->inlineJob([$a->id, $u->id, $c->id]));

        $campaign = Campaign::sole();
        $this->assertEqualsCanonicalizing(['a@example.com', 'c@example.com'], $this->sentTo);
        $this->assertSame(2, $campaign->sent_count);
        $this->assertSame(1, $campaign->skipped_count);
        $this->assertSame(3, $campaign->total_count);
        $this->assertSame(
            [$a->id, $c->id],
            CampaignMessage::where('campaign_id', $campaign->id)->where('status', 'sent')->orderBy('prospect_id')->pluck('prospect_id')->all()
        );
    }

    public function test_multi_generation_run_keeps_the_audience_and_sends_to_everyone_once(): void
    {
        config(['mailing.run_budget_seconds' => 0]);
        [$a, $u, $c] = $this->threeProspects();
        $ids = [$a->id, $u->id, $c->id];

        $job  = $this->inlineJob($ids);
        $runs = 0;
        while ($job !== null && $runs < 10) {
            Queue::fake();
            $this->handle($job);
            $runs++;
            $pushed = Queue::pushed(ExecuteCampaignJob::class);
            $job    = $pushed->isEmpty() ? null : $pushed->first();
        }

        $this->assertNull($job);
        $this->assertGreaterThan(1, $runs);
        $this->assertSame(1, Campaign::count());
        $campaign = Campaign::first();
        $this->assertSame(AudienceType::MANUAL, $campaign->audience_type);
        $this->assertSame(['prospect_ids' => $ids], $campaign->audience_filters);
        $this->assertSame(['a@example.com', 'c@example.com'], $this->sentTo);
        $this->assertSame(2, $campaign->sent_count);
    }
}
