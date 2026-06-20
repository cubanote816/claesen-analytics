<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\TemplateCategory;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\EmailTemplate;
use Tests\TestCase;

class BackfillPreferenceSnapshotsCommandTest extends TestCase
{
    use RefreshDatabase;

    // ─── Dry-run ──────────────────────────────────────────────────────────────

    public function test_dry_run_does_not_modify_database(): void
    {
        $template = EmailTemplate::factory()->asOffers()->create();
        $campaign = Campaign::factory()->withoutSnapshots()->create([
            'template_id' => $template->id,
        ]);

        $this->artisan('mailing:backfill-preference-snapshots')
            ->assertExitCode(0);

        $this->assertNull($campaign->fresh()->template_category_snapshot);
        $this->assertNull($campaign->fresh()->preference_category_snapshot);
    }

    // ─── Apply mode ───────────────────────────────────────────────────────────

    public function test_apply_updates_campaigns_with_null_snapshots(): void
    {
        $template = EmailTemplate::factory()->asOffers()->create();
        $campaign = Campaign::factory()->withoutSnapshots()->create([
            'template_id' => $template->id,
        ]);

        $this->artisan('mailing:backfill-preference-snapshots', ['--apply' => true])
            ->assertExitCode(0);

        $campaign->refresh();
        $this->assertSame(TemplateCategory::COMMERCIAL->value, $campaign->template_category_snapshot);
        $this->assertSame('offers', $campaign->preference_category_snapshot);
    }

    public function test_apply_updates_transactional_campaign(): void
    {
        $template = EmailTemplate::factory()->transactional()->create();
        $campaign = Campaign::factory()->withoutSnapshots()->create([
            'template_id' => $template->id,
        ]);

        $this->artisan('mailing:backfill-preference-snapshots', ['--apply' => true])
            ->assertExitCode(0);

        $campaign->refresh();
        $this->assertSame(TemplateCategory::TRANSACTIONAL->value, $campaign->template_category_snapshot);
        $this->assertNull($campaign->preference_category_snapshot);
    }

    // ─── Blocking condition ───────────────────────────────────────────────────

    public function test_exits_failure_when_a_campaign_is_in_sending_state(): void
    {
        Campaign::factory()->create(['status' => CampaignStatus::SENDING]);

        $this->artisan('mailing:backfill-preference-snapshots', ['--apply' => true])
            ->assertExitCode(1);
    }

    // ─── Idempotency ──────────────────────────────────────────────────────────

    public function test_already_valid_campaigns_are_skipped(): void
    {
        $template = EmailTemplate::factory()->asNewsletter()->create();
        $campaign = Campaign::factory()->commercial('newsletter')->create([
            'template_id' => $template->id,
        ]);

        $this->artisan('mailing:backfill-preference-snapshots', ['--apply' => true])
            ->assertExitCode(0);

        // Values must not change
        $campaign->refresh();
        $this->assertSame(TemplateCategory::COMMERCIAL->value, $campaign->template_category_snapshot);
        $this->assertSame('newsletter', $campaign->preference_category_snapshot);
    }

    public function test_running_apply_twice_is_safe(): void
    {
        $template = EmailTemplate::factory()->asOffers()->create();
        Campaign::factory()->withoutSnapshots()->create(['template_id' => $template->id]);

        $this->artisan('mailing:backfill-preference-snapshots', ['--apply' => true])->assertExitCode(0);
        $this->artisan('mailing:backfill-preference-snapshots', ['--apply' => true])->assertExitCode(0);
    }

    // ─── Approved campaigns reverted to review ────────────────────────────────

    public function test_approved_commercial_campaign_without_pref_category_is_reverted_to_review(): void
    {
        // Template has no preference_category — approval should be reverted
        $approver = \Modules\Core\Models\User::factory()->create();
        $template = EmailTemplate::factory()->create([
            'category'            => TemplateCategory::COMMERCIAL,
            'preference_category' => null,
        ]);

        $campaign = Campaign::factory()->approved()->create([
            'template_id'                  => $template->id,
            'template_category_snapshot'   => null,
            'preference_category_snapshot' => null,
            'approved_by'                  => $approver->id,
        ]);

        // This command exits FAILURE because of the unresolvable case (revert = 1, exit 0)
        // The reverted campaign is counted as "reverted", unresolvable separately.
        // In this case template has null pref_cat and is commercial → revert.
        $this->artisan('mailing:backfill-preference-snapshots', ['--apply' => true]);

        $campaign->refresh();
        $this->assertSame(CampaignStatus::REVIEW->value, $campaign->status->value);
        $this->assertNull($campaign->approved_by);
        $this->assertNull($campaign->approved_at);
    }

    // ─── No template → unresolvable ───────────────────────────────────────────

    public function test_campaign_without_template_is_unresolvable_and_exits_failure(): void
    {
        Campaign::factory()->withoutSnapshots()->create([
            'template_id' => null,
        ]);

        $this->artisan('mailing:backfill-preference-snapshots', ['--apply' => true])
            ->assertExitCode(1);
    }

    // ─── Inconsistent snapshot data ───────────────────────────────────────────

    public function test_transactional_with_non_null_pref_snapshot_is_fixed(): void
    {
        // Inconsistent state: transactional category but pref_category set (shouldn't happen)
        $template = EmailTemplate::factory()->transactional()->create();
        $campaign = Campaign::factory()->create([
            'template_id'                  => $template->id,
            'template_category_snapshot'   => TemplateCategory::TRANSACTIONAL->value,
            'preference_category_snapshot' => 'offers', // inconsistent
        ]);

        $this->artisan('mailing:backfill-preference-snapshots', ['--apply' => true])
            ->assertExitCode(0);

        $campaign->refresh();
        $this->assertNull($campaign->preference_category_snapshot);
    }
}
