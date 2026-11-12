<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\FollowUpTrigger;
use Modules\Mailing\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use Modules\Mailing\Filament\Resources\CampaignResource\Pages\ViewCampaign;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\EmailTemplate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-669: an approved campaign without scheduled_at is never picked up by
 * mailing:dispatch-scheduled, so it needs an explicit "Send now" action.
 * Approval itself never sends: follow-up children are approved without a date
 * before a parent can select them.
 */
class SendNowCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        foreach (['super_admin', 'admin', 'campaign_manager', 'marketer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function approvedUnscheduled(array $attrs = []): Campaign
    {
        return Campaign::factory()->create(array_merge([
            'status'       => CampaignStatus::APPROVED,
            'template_id'  => EmailTemplate::factory()->create()->id,
            'scheduled_at' => null,
        ], $attrs));
    }

    private function parentWithFollowUp(Campaign $child, array $attrs = []): Campaign
    {
        return Campaign::factory()->create(array_merge([
            'status'               => CampaignStatus::APPROVED,
            'followup_campaign_id' => $child->id,
            'followup_trigger'     => FollowUpTrigger::CLICKED,
            'followup_delay_hours' => 24,
        ], $attrs));
    }

    // -------------------------------------------------------------------------
    // S1 + S2: the action sends exactly once
    // -------------------------------------------------------------------------

    public function test_send_now_on_view_page_claims_and_dispatches_once(): void
    {
        $this->actingAsRole('admin');
        $campaign = $this->approvedUnscheduled();

        Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
            ->assertActionVisible('send_now')
            ->callAction('send_now')
            ->assertHasNoActionErrors()
            ->assertNotified(__('mailing::resource.notifications.sent_now'));

        $this->assertSame(CampaignStatus::SENDING, $campaign->fresh()->status);
        Queue::assertPushed(ExecuteCampaignJob::class, 1);
        Queue::assertPushed(ExecuteCampaignJob::class, fn (ExecuteCampaignJob $job) =>
            $job->campaignId === $campaign->id
            && $job->overrideProspectIds === null
            && $job->isWinnerSend === false
        );
    }

    public function test_send_now_in_campaign_list_claims_and_dispatches_once(): void
    {
        $this->actingAsRole('campaign_manager');
        $campaign = $this->approvedUnscheduled();

        Livewire::test(ListCampaigns::class)
            ->assertTableActionVisible('send_now', $campaign)
            ->callTableAction('send_now', $campaign)
            ->assertNotified(__('mailing::resource.notifications.sent_now'));

        $this->assertSame(CampaignStatus::SENDING, $campaign->fresh()->status);
        Queue::assertPushed(ExecuteCampaignJob::class, 1);
        Queue::assertPushed(ExecuteCampaignJob::class, fn (ExecuteCampaignJob $job) => $job->campaignId === $campaign->id);
    }

    public function test_lost_claim_dispatches_nothing_and_leaves_status_unchanged(): void
    {
        $this->actingAsRole('admin');
        $campaign = $this->approvedUnscheduled();

        $page = Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
            ->assertActionVisible('send_now');

        // Another click / process claims the campaign after the page was rendered.
        // Livewire re-fetches the record, so the action is no longer runnable.
        Campaign::whereKey($campaign->id)->update(['status' => CampaignStatus::SENDING->value]);

        $page->callAction('send_now');

        $this->assertSame(CampaignStatus::SENDING, $campaign->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_queue_failure_releases_the_claim_so_the_send_can_be_retried(): void
    {
        $this->actingAsRole('admin');
        $campaign = $this->approvedUnscheduled();
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('queue down'));

        Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
            ->callAction('send_now')
            ->assertNotified(__('mailing::resource.notifications.send_now_failed'));

        $fresh = $campaign->fresh();
        $this->assertSame(CampaignStatus::APPROVED, $fresh->status);
        $this->assertTrue($fresh->canBeSentNow());
    }

    public function test_only_one_of_two_concurrent_claims_wins(): void
    {
        $campaign = $this->approvedUnscheduled();
        $first    = Campaign::find($campaign->id);
        $second   = Campaign::find($campaign->id);

        $this->assertTrue($first->claimForImmediateSend());
        $this->assertFalse($second->claimForImmediateSend());
        $this->assertSame(CampaignStatus::SENDING, $campaign->fresh()->status);
    }

    public function test_claim_refuses_a_campaign_that_gained_a_send_date(): void
    {
        $campaign = $this->approvedUnscheduled();
        Campaign::whereKey($campaign->id)->update(['scheduled_at' => now()->addDay()]);

        $this->assertFalse($campaign->claimForImmediateSend());
        $this->assertSame(CampaignStatus::APPROVED, $campaign->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // S3: when the action is not available
    // -------------------------------------------------------------------------

    public function test_hidden_when_campaign_has_a_send_date(): void
    {
        $this->actingAsRole('admin');
        $campaign = $this->approvedUnscheduled(['scheduled_at' => now()->addDay()]);

        Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
            ->assertActionHidden('send_now');
        Livewire::test(ListCampaigns::class)
            ->assertTableActionHidden('send_now', $campaign);
    }

    public static function nonApprovedStatuses(): array
    {
        return [
            'draft'     => [CampaignStatus::DRAFT],
            'review'    => [CampaignStatus::REVIEW],
            'sending'   => [CampaignStatus::SENDING],
            'completed' => [CampaignStatus::COMPLETED],
            'failed'    => [CampaignStatus::FAILED],
            'cancelled' => [CampaignStatus::CANCELLED],
        ];
    }

    #[DataProvider('nonApprovedStatuses')]
    public function test_hidden_when_campaign_is_not_approved(CampaignStatus $status): void
    {
        // super_admin bypasses the policy via Gate::before, so the domain guard must hold on its own.
        $this->actingAsRole('super_admin');
        $campaign = $this->approvedUnscheduled(['status' => $status]);

        Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
            ->assertActionHidden('send_now');
    }

    public function test_hidden_when_campaign_is_a_follow_up_child(): void
    {
        $this->actingAsRole('super_admin');
        $child = $this->approvedUnscheduled();
        $this->parentWithFollowUp($child);

        Livewire::test(ViewCampaign::class, ['record' => $child->id])
            ->assertActionHidden('send_now');
        Livewire::test(ListCampaigns::class)
            ->assertTableActionHidden('send_now', $child);
    }

    public function test_hidden_while_the_follow_up_job_is_queued(): void
    {
        // dispatch-followups marks the parent dispatched when it queues the child job;
        // the child stays approved until the worker runs, so it must not be sendable.
        $this->actingAsRole('super_admin');
        $child = $this->approvedUnscheduled();
        $this->parentWithFollowUp($child, [
            'status'                 => CampaignStatus::COMPLETED,
            'followup_dispatched_at' => now()->subMinute(),
        ]);

        Livewire::test(ViewCampaign::class, ['record' => $child->id])
            ->assertActionHidden('send_now');
        Livewire::test(ListCampaigns::class)
            ->assertTableActionHidden('send_now', $child);
        Queue::assertNothingPushed();
    }

    public function test_visible_when_follow_up_parent_was_cancelled(): void
    {
        $this->actingAsRole('admin');
        $child = $this->approvedUnscheduled();
        $this->parentWithFollowUp($child, ['status' => CampaignStatus::CANCELLED]);

        Livewire::test(ViewCampaign::class, ['record' => $child->id])
            ->assertActionVisible('send_now');
    }

    public function test_hidden_for_marketer(): void
    {
        $this->actingAsRole('marketer');
        $campaign = $this->approvedUnscheduled();

        Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
            ->assertActionHidden('send_now');
        Livewire::test(ListCampaigns::class)
            ->assertTableActionHidden('send_now', $campaign);
    }

    // -------------------------------------------------------------------------
    // S4: helper text describes the real behavior
    // -------------------------------------------------------------------------

    public function test_scheduled_at_helper_points_to_send_now_instead_of_promising_auto_send(): void
    {
        foreach (['en', 'nl'] as $locale) {
            $helper = __('mailing::resource.fields.scheduled_at_helper', [], $locale);
            $action = __('mailing::resource.actions.send_now', [], $locale);

            $this->assertStringContainsString($action, $helper, "[$locale] helper must name the send-now action");
            $this->assertStringNotContainsStringIgnoringCase('immediately after approval', $helper);
            $this->assertStringNotContainsStringIgnoringCase('onmiddellijk na goedkeuring', $helper);
        }
    }
}
