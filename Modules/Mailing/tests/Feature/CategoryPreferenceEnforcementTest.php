<?php

namespace Modules\Mailing\Tests\Feature;

use App\Contracts\MarketingCampaignInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\TemplateCategory;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\ContactPreference;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Services\SuppressionService;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Tests\TestCase;

class CategoryPreferenceEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mailing.send_delay_ms' => 0]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function region(): Region
    {
        return Region::firstOrCreate(['name' => 'Test Region'], ['slug' => 'test-region']);
    }

    private function prospect(): Prospect
    {
        $region = $this->region();
        $p = Prospect::create([
            'name'      => 'TestClub-' . uniqid(),
            'region_id' => $region->id,
        ]);
        ProspectLocation::create([
            'prospect_id'  => $p->id,
            'contact_type' => 'main',
            'email'        => 'club-' . $p->id . '@example.com',
        ]);
        return $p;
    }

    private function mockMailer(bool $returns = true): void
    {
        $mock = Mockery::mock(MarketingCampaignInterface::class);
        $mock->shouldReceive('sendCampaign')->andReturn($returns);
        $this->app->instance(MarketingCampaignInterface::class, $mock);
    }

    private function runJob(Campaign $campaign, ?array $prospectIds = null): void
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

    // ─── Job snapshot guard ───────────────────────────────────────────────────

    public function test_job_fails_campaign_when_template_category_snapshot_is_null(): void
    {
        $this->mockMailer();
        $campaign = Campaign::factory()->withoutSnapshots()->approved()->create();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/null or unknown template_category_snapshot/');

        $this->runJob($campaign, []);
    }

    public function test_job_marks_campaign_failed_when_template_category_is_null(): void
    {
        $this->mockMailer();
        $campaign = Campaign::factory()->withoutSnapshots()->approved()->create();

        try {
            $this->runJob($campaign, []);
        } catch (\DomainException) {}

        $this->assertDatabaseHas('mailing_campaigns', [
            'id'     => $campaign->id,
            'status' => CampaignStatus::FAILED->value,
        ]);
    }

    public function test_job_fails_campaign_when_commercial_has_null_preference_category(): void
    {
        $this->mockMailer();
        $campaign = Campaign::factory()->approved()->create([
            'template_category_snapshot'   => TemplateCategory::COMMERCIAL->value,
            'preference_category_snapshot' => null,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/commercial campaign has invalid preference_category_snapshot/');

        $this->runJob($campaign, []);
    }

    public function test_job_fails_campaign_when_commercial_has_unknown_preference_category(): void
    {
        $this->mockMailer();
        $campaign = Campaign::factory()->approved()->create([
            'template_category_snapshot'   => TemplateCategory::COMMERCIAL->value,
            'preference_category_snapshot' => 'invalid_unknown_category',
        ]);

        $this->expectException(\DomainException::class);

        $this->runJob($campaign, []);
    }

    public function test_job_fails_campaign_when_transactional_has_non_null_preference_category(): void
    {
        $this->mockMailer();
        $campaign = Campaign::factory()->approved()->create([
            'template_category_snapshot'   => TemplateCategory::TRANSACTIONAL->value,
            'preference_category_snapshot' => 'offers',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/transactional campaign has unexpected preference_category_snapshot/');

        $this->runJob($campaign, []);
    }

    public function test_job_sends_commercial_campaign_with_valid_preference_category(): void
    {
        $this->mockMailer(true);
        $prospect = $this->prospect();
        $campaign = Campaign::factory()->commercial('offers')->approved()->create();

        $this->runJob($campaign, [$prospect->id]);

        $this->assertDatabaseHas('mailing_campaigns', [
            'id'     => $campaign->id,
            'status' => CampaignStatus::COMPLETED->value,
        ]);
    }

    public function test_job_sends_transactional_campaign_with_null_preference_category(): void
    {
        $this->mockMailer(true);
        $prospect = $this->prospect();
        $campaign = Campaign::factory()->transactional()->approved()->create();

        $this->runJob($campaign, [$prospect->id]);

        $this->assertDatabaseHas('mailing_campaigns', [
            'id'     => $campaign->id,
            'status' => CampaignStatus::COMPLETED->value,
        ]);
    }

    // ─── Category preference opt-out enforcement ──────────────────────────────

    public function test_opted_out_prospect_is_skipped_with_category_error_message(): void
    {
        $this->mockMailer(true);
        $prospect = $this->prospect();

        ContactPreference::create([
            'prospect_id' => $prospect->id,
            'category'    => 'offers',
            'subscribed'  => false,
        ]);

        $campaign = Campaign::factory()->commercial('offers')->approved()->create();
        $this->runJob($campaign, [$prospect->id]);

        $this->assertDatabaseHas('mailing_messages', [
            'campaign_id'   => $campaign->id,
            'prospect_id'   => $prospect->id,
            'status'        => 'skipped',
            'error_message' => 'category_opted_out:offers',
        ]);
        $this->assertSame(1, $campaign->fresh()->skipped_count);
    }

    public function test_not_opted_out_prospect_receives_email(): void
    {
        $this->mockMailer(true);
        $prospect = $this->prospect();

        // No ContactPreference row = subscribed (opt-out model)
        $campaign = Campaign::factory()->commercial('offers')->approved()->create();
        $this->runJob($campaign, [$prospect->id]);

        $this->assertDatabaseHas('mailing_messages', [
            'campaign_id' => $campaign->id,
            'prospect_id' => $prospect->id,
            'status'      => 'sent',
        ]);
    }

    public function test_opted_in_prospect_receives_email(): void
    {
        $this->mockMailer(true);
        $prospect = $this->prospect();

        // Explicit subscribed=true row must also receive email
        ContactPreference::create([
            'prospect_id' => $prospect->id,
            'category'    => 'offers',
            'subscribed'  => true,
        ]);

        $campaign = Campaign::factory()->commercial('offers')->approved()->create();
        $this->runJob($campaign, [$prospect->id]);

        $this->assertDatabaseHas('mailing_messages', [
            'campaign_id' => $campaign->id,
            'prospect_id' => $prospect->id,
            'status'      => 'sent',
        ]);
    }

    public function test_transactional_campaign_ignores_category_preferences(): void
    {
        $this->mockMailer(true);
        $prospect = $this->prospect();

        // Prospect opted out of everything — transactional must bypass preference checks
        ContactPreference::create([
            'prospect_id' => $prospect->id,
            'category'    => 'newsletter',
            'subscribed'  => false,
        ]);

        $campaign = Campaign::factory()->transactional()->approved()->create();
        $this->runJob($campaign, [$prospect->id]);

        $this->assertDatabaseHas('mailing_messages', [
            'campaign_id' => $campaign->id,
            'prospect_id' => $prospect->id,
            'status'      => 'sent',
        ]);
    }

    public function test_category_opt_out_has_precedence_over_sending_even_with_email(): void
    {
        // Opted-out prospect with valid email: must be skipped, never reach mailer
        $this->mockMailer(false); // mailer returns false — must NOT be called
        $prospect = $this->prospect();

        ContactPreference::create([
            'prospect_id' => $prospect->id,
            'category'    => 'newsletter',
            'subscribed'  => false,
        ]);

        $campaign = Campaign::factory()->commercial('newsletter')->approved()->create();

        $mailerMock = Mockery::mock(MarketingCampaignInterface::class);
        $mailerMock->shouldNotReceive('sendCampaign');
        $this->app->instance(MarketingCampaignInterface::class, $mailerMock);

        $this->runJob($campaign, [$prospect->id]);

        $this->assertDatabaseHas('mailing_messages', [
            'status'        => 'skipped',
            'error_message' => 'category_opted_out:newsletter',
        ]);
    }

    public function test_suppression_check_has_precedence_over_category_opt_out(): void
    {
        // Suppression must be checked before category preferences
        $this->mockMailer(true);
        $prospect = $this->prospect();
        $email    = $prospect->locations()->first()->email;

        app(SuppressionService::class)->suppress($email, \Modules\Mailing\Enums\SuppressionReason::HARD_BOUNCE);

        ContactPreference::create([
            'prospect_id' => $prospect->id,
            'category'    => 'offers',
            'subscribed'  => false,
        ]);

        $campaign = Campaign::factory()->commercial('offers')->approved()->create();
        $this->runJob($campaign, [$prospect->id]);

        // Status must be 'skipped' with suppression reason, not 'category_opted_out'
        $this->assertDatabaseHas('mailing_messages', [
            'campaign_id'   => $campaign->id,
            'status'        => 'skipped',
            'error_message' => 'suppressed: hard_bounce',
        ]);
    }

    // ─── Campaign::buildSnapshotFrom() ────────────────────────────────────────

    public function test_build_snapshot_from_captures_all_fields_from_template(): void
    {
        $template = EmailTemplate::factory()->asOffers()->create([
            'name'    => 'My Template',
            'subject' => 'Hello world',
            'body'    => '<p>Body here</p>',
        ]);

        $snapshot = Campaign::buildSnapshotFrom($template);

        $this->assertSame('My Template', $snapshot['template_name']);
        $this->assertSame('Hello world', $snapshot['subject_snapshot']);
        $this->assertSame('<p>Body here</p>', $snapshot['body_snapshot']);
        $this->assertSame(TemplateCategory::COMMERCIAL->value, $snapshot['template_category_snapshot']);
        $this->assertSame('offers', $snapshot['preference_category_snapshot']);
    }

    public function test_build_snapshot_forces_null_preference_for_transactional_template(): void
    {
        $template = EmailTemplate::factory()->transactional()->create();

        $snapshot = Campaign::buildSnapshotFrom($template);

        $this->assertSame(TemplateCategory::TRANSACTIONAL->value, $snapshot['template_category_snapshot']);
        $this->assertNull($snapshot['preference_category_snapshot']);
    }

    public function test_build_snapshot_throws_for_invalid_preference_category(): void
    {
        // Bypass the saving hook to create a template with bad data (simulates legacy DB state)
        $template = EmailTemplate::factory()->create();
        $template->preference_category = 'completely_invalid';
        $template->category = TemplateCategory::COMMERCIAL;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid preference_category/');

        Campaign::buildSnapshotFrom($template);
    }

    // ─── Campaign::transitionTo(APPROVED) ────────────────────────────────────

    public function test_transition_to_approved_sets_snapshots_server_side(): void
    {
        $template = EmailTemplate::factory()->asOffers()->create();
        $campaign = Campaign::factory()->inReview()->create(['template_id' => $template->id]);

        $campaign->transitionTo(CampaignStatus::APPROVED, 1);

        $campaign->refresh();
        $this->assertSame(TemplateCategory::COMMERCIAL->value, $campaign->template_category_snapshot);
        $this->assertSame('offers', $campaign->preference_category_snapshot);
        $this->assertNotNull($campaign->approved_at);
        $this->assertSame(1, $campaign->approved_by);
    }

    public function test_transition_to_approved_throws_for_commercial_template_without_preference_category(): void
    {
        // Template saved without preference_category (null) — approval must be blocked
        $template = EmailTemplate::factory()->create([
            'category'            => TemplateCategory::COMMERCIAL,
            'preference_category' => null,
        ]);
        $campaign = Campaign::factory()->inReview()->create(['template_id' => $template->id]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/commercial campaigns require a valid preference category/');

        $campaign->transitionTo(CampaignStatus::APPROVED, 1);
    }

    public function test_transition_to_approved_throws_when_no_template_linked(): void
    {
        $campaign = Campaign::factory()->inReview()->create(['template_id' => null]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/cannot approve — no template linked/');

        $campaign->transitionTo(CampaignStatus::APPROVED, 1);
    }

    // ─── EmailTemplate saving hook ────────────────────────────────────────────

    public function test_saving_transactional_template_clears_preference_category(): void
    {
        $template = EmailTemplate::factory()->create([
            'category'            => TemplateCategory::TRANSACTIONAL,
            'preference_category' => null,
        ]);

        // Explicitly try to set a preference_category on a transactional template
        $template->preference_category = 'offers';
        $template->save();

        $this->assertNull($template->fresh()->preference_category);
    }

    public function test_saving_template_with_invalid_preference_category_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EmailTemplate::factory()->create([
            'category'            => TemplateCategory::COMMERCIAL,
            'preference_category' => 'not_a_real_category',
        ]);
    }

    // ─── sendToProspects snapshot validation ─────────────────────────────────

    public function test_job_throws_when_body_snapshot_is_empty(): void
    {
        $this->mockMailer(true);
        $prospect = $this->prospect();
        $campaign = Campaign::factory()->commercial('offers')->approved()->create([
            'body_snapshot' => '',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/body_snapshot is required/');

        $this->runJob($campaign, [$prospect->id]);
    }
}
