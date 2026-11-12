<?php

namespace Modules\Mailing\Tests\Feature;

use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Filament\Resources\CampaignResource\Pages\ViewCampaign;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Livewire\CampaignProgressWatcher;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Support\CampaignSendTracker;
use Modules\Prospects\Filament\Resources\Prospects\Pages\ManageProspects;
use Modules\Prospects\Models\Prospect;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-680: session-scoped poller that reports tracked campaigns reaching a final state.
 */
class CampaignProgressWatcherTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role = 'admin'): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function campaign(CampaignStatus $status, array $attrs = []): Campaign
    {
        return Campaign::factory()->create(array_merge([
            'status'        => $status,
            'description'   => 'Fair follow-up',
            'sent_count'    => 3,
            'failed_count'  => 1,
            'skipped_count' => 2,
        ], $attrs));
    }

    public function test_renders_without_poll_when_nothing_is_tracked(): void
    {
        $this->actingAsRole();

        Livewire::test(CampaignProgressWatcher::class)
            ->assertDontSeeHtml('wire:poll')
            ->call('check')
            ->assertNotNotified();
    }

    // A launch on the same page tracks the send after the watcher rendered: the event
    // must make it start polling without a page reload.
    public function test_starts_polling_when_a_send_is_tracked_on_the_same_page(): void
    {
        $this->actingAsRole();
        $campaign = $this->campaign(CampaignStatus::SENDING);

        $watcher = Livewire::test(CampaignProgressWatcher::class)->assertDontSeeHtml('wire:poll');

        CampaignSendTracker::trackCampaign($campaign->id);

        $watcher->dispatch('campaign-send-tracked')->assertSeeHtml('wire:poll.5s');
    }

    public function test_polls_while_sending_and_keeps_tracking_without_a_toast(): void
    {
        $this->actingAsRole();
        $campaign = $this->campaign(CampaignStatus::SENDING);
        CampaignSendTracker::trackCampaign($campaign->id);

        Livewire::test(CampaignProgressWatcher::class)
            ->assertSeeHtml('wire:poll.5s')
            ->call('check')
            ->assertNotNotified()
            ->assertSeeHtml('wire:poll.5s');

        $this->assertCount(1, CampaignSendTracker::pending());
    }

    public function test_completed_campaign_shows_one_toast_and_is_forgotten(): void
    {
        $this->actingAsRole();
        $campaign = $this->campaign(CampaignStatus::COMPLETED);
        CampaignSendTracker::trackCampaign($campaign->id);

        Livewire::test(CampaignProgressWatcher::class)
            ->call('check')
            ->assertNotified(__('mailing::resource.notifications.campaign_finished_title', ['description' => 'Fair follow-up']))
            ->assertDontSeeHtml('wire:poll');

        $this->assertSame([], CampaignSendTracker::pending());
    }

    public function test_second_tick_sends_nothing(): void
    {
        $this->actingAsRole();
        $campaign = $this->campaign(CampaignStatus::COMPLETED);
        CampaignSendTracker::trackCampaign($campaign->id);

        Livewire::test(CampaignProgressWatcher::class)
            ->call('check')
            ->call('check');

        $this->assertSame([], CampaignSendTracker::pending());
    }

    public function test_failed_campaign_shows_failed_toast(): void
    {
        $this->actingAsRole();
        $campaign = $this->campaign(CampaignStatus::FAILED);
        CampaignSendTracker::trackCampaign($campaign->id);

        Livewire::test(CampaignProgressWatcher::class)
            ->call('check')
            ->assertNotified(__('mailing::resource.notifications.campaign_failed_title', ['description' => 'Fair follow-up']));

        $this->assertSame([], CampaignSendTracker::pending());
    }

    public function test_dispatch_key_resolves_to_the_campaign_the_job_created(): void
    {
        $user = $this->actingAsRole();
        CampaignSendTracker::trackDispatch('key-123');

        $component = Livewire::test(CampaignProgressWatcher::class);

        // The job has not created the campaign yet: keep waiting.
        $component->call('check')->assertNotNotified();
        $this->assertCount(1, CampaignSendTracker::pending());

        $this->campaign(CampaignStatus::SENDING, ['dispatch_key' => 'key-123', 'created_by' => $user->id]);
        $component->call('check')->assertNotNotified();
        $this->assertCount(1, CampaignSendTracker::pending());

        Campaign::where('dispatch_key', 'key-123')->update(['status' => CampaignStatus::COMPLETED->value]);
        $component->call('check')
            ->assertNotified(__('mailing::resource.notifications.campaign_finished_title', ['description' => 'Fair follow-up']));
        $this->assertSame([], CampaignSendTracker::pending());
    }

    public function test_dispatch_key_of_another_users_campaign_is_ignored(): void
    {
        $this->actingAsRole();
        $other = User::factory()->create();
        $this->campaign(CampaignStatus::COMPLETED, ['dispatch_key' => 'key-other', 'created_by' => $other->id]);
        CampaignSendTracker::trackDispatch('key-other');

        Livewire::test(CampaignProgressWatcher::class)
            ->call('check')
            ->assertNotNotified();
    }

    public function test_untracked_and_other_users_campaigns_are_never_reported(): void
    {
        $this->actingAsRole();
        $mine = $this->campaign(CampaignStatus::COMPLETED);
        CampaignSendTracker::trackCampaign($mine->id);
        $this->assertCount(1, CampaignSendTracker::pending());

        // A different user's session has its own tracker: it sees nothing of $owner's.
        $this->actingAs(User::factory()->create());
        $this->assertSame([], CampaignSendTracker::pending());

        $this->campaign(CampaignStatus::COMPLETED); // never tracked
        Livewire::test(CampaignProgressWatcher::class)
            ->call('check')
            ->assertNotNotified();
    }

    public function test_entries_that_never_resolve_are_dropped_after_the_timeout(): void
    {
        $this->actingAsRole();
        CampaignSendTracker::trackDispatch('ghost');

        $this->travel(31)->minutes();

        $this->assertSame([], CampaignSendTracker::pending());
    }

    public function test_tracker_is_capped(): void
    {
        $this->actingAsRole();
        foreach (range(1, 30) as $i) {
            CampaignSendTracker::trackCampaign($i);
        }

        $this->assertCount(20, CampaignSendTracker::pending());
    }

    public function test_bulk_send_toast_is_persistent_and_tracks_the_dispatch_key(): void
    {
        Queue::fake();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $template  = EmailTemplate::factory()->create();
        $prospects = Prospect::factory()->count(2)->create();

        Livewire::actingAs($user)
            ->test(ManageProspects::class)
            ->callTableBulkAction('execute_campaign', $prospects->all(), [
                'description' => 'Fair follow-up',
                'template_id' => $template->id,
            ])
            ->assertDispatched('campaign-send-tracked')
            ->assertNotified(Notification::make()
                ->title(__('prospects::resource.notifications.campaign_started.title'))
                ->body(__('prospects::resource.notifications.campaign_started.body', ['count' => 2]))
                ->success()
                ->persistent());

        $keys = [];
        Queue::assertPushed(ExecuteCampaignJob::class, function (ExecuteCampaignJob $job) use (&$keys) {
            $keys[] = $job->dispatchKey;

            return true;
        });

        $this->assertSame(
            [['type' => 'dispatch', 'value' => $keys[0]]],
            array_map(fn ($e) => ['type' => $e['type'], 'value' => $e['value']], CampaignSendTracker::pending()),
        );
    }

    public function test_send_now_toast_is_persistent_and_tracks_the_campaign(): void
    {
        Queue::fake();
        $this->actingAsRole();
        $campaign = $this->campaign(CampaignStatus::APPROVED, [
            'template_id'  => EmailTemplate::factory()->create()->id,
            'scheduled_at' => null,
        ]);

        Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
            ->callAction('send_now')
            ->assertDispatched('campaign-send-tracked')
            ->assertNotified(Notification::make()
                ->title(__('mailing::resource.notifications.sent_now'))
                ->body(__('mailing::resource.notifications.sent_now_body'))
                ->success()
                ->persistent());

        $this->assertSame(
            [['type' => 'campaign', 'value' => $campaign->id]],
            array_map(fn ($e) => ['type' => $e['type'], 'value' => $e['value']], CampaignSendTracker::pending()),
        );
    }
}
