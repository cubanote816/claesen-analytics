<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\AudienceType;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Prospects\Filament\Resources\Prospects\Pages\ManageProspects;
use Modules\Prospects\Models\Prospect;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StartMailingCampaignTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** @return array<string, array{string}> */
    public static function approverRoles(): array
    {
        return [
            'super_admin'      => ['super_admin'],
            'admin'            => ['admin'],
            'campaign_manager' => ['campaign_manager'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function creatorRoles(): array
    {
        return [
            'marketer' => ['marketer'],
            'approver' => ['campaign_manager'],
        ];
    }

    private function runAction(User $user, array $prospects, array $data): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($user)
            ->test(ManageProspects::class)
            ->callTableBulkAction('execute_campaign', $prospects, $data);
    }

    #[DataProvider('approverRoles')]
    public function test_approvers_send_immediately(string $role): void
    {
        Queue::fake();
        $template  = EmailTemplate::factory()->create();
        $prospects = Prospect::factory()->count(2)->create();

        $this->runAction($this->userWithRole($role), $prospects->all(), [
            'description' => 'Fair follow-up',
            'template_id' => $template->id,
        ])->assertNotified(__('prospects::resource.notifications.campaign_started.title'));

        Queue::assertPushed(ExecuteCampaignJob::class, 1);
        Queue::assertPushed(ExecuteCampaignJob::class, fn (ExecuteCampaignJob $job) => $job->prospectIds === $prospects->pluck('id')->all()
            && $job->templateId === $template->id
            && $job->description === 'Fair follow-up');
        $this->assertSame(0, Campaign::count());
    }

    public function test_select_all_minus_exclusion_targets_every_other_prospect(): void
    {
        Queue::fake();
        $template  = EmailTemplate::factory()->create();
        $prospects = Prospect::factory()->count(4)->create();
        $excluded  = $prospects->last();

        // The state Filament's "select all" sends: tracking deselected, none selected.
        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ManageProspects::class)
            ->set('isTrackingDeselectedTableRecords', true)
            ->set('selectedTableRecords', [])
            ->set('deselectedTableRecords', [(string) $excluded->id])
            ->callAction(TestAction::make('execute_campaign')->table()->bulk(), [
                'description' => 'Select all',
                'template_id' => $template->id,
            ]);

        Queue::assertPushed(ExecuteCampaignJob::class, 1);
        Queue::assertPushed(ExecuteCampaignJob::class, function (ExecuteCampaignJob $job) use ($prospects, $excluded) {
            $expected = $prospects->pluck('id')->diff([$excluded->id])->sort()->values()->all();

            return collect($job->prospectIds)->sort()->values()->all() === $expected;
        });
    }

    public function test_marketer_sends_campaign_to_review(): void
    {
        Queue::fake();
        $marketer     = $this->userWithRole('marketer');
        $template     = EmailTemplate::factory()->create();
        $subscribed   = Prospect::factory()->count(2)->create();
        $unsubscribed = Prospect::factory()->create(['unsubscribed_at' => now()]);

        $this->runAction($marketer, [...$subscribed->all(), $unsubscribed], [
            'description' => 'Fair follow-up',
            'template_id' => $template->id,
        ])->assertNotified(__('prospects::resource.notifications.campaign_sent_to_review.title'));

        Queue::assertNothingPushed();
        $this->assertSame(1, Campaign::count());

        $campaign = Campaign::first();
        $this->assertSame(CampaignStatus::REVIEW, $campaign->status);
        $this->assertSame(AudienceType::MANUAL, $campaign->audience_type);
        $this->assertSame(['prospect_ids' => $subscribed->pluck('id')->map(fn ($id) => (int) $id)->all()], $campaign->audience_filters);
        $this->assertSame($template->id, $campaign->template_id);
        $this->assertSame($template->name, $campaign->template_name);
        $this->assertSame($template->subject, $campaign->subject_snapshot);
        $this->assertSame('Fair follow-up', $campaign->description);
        $this->assertSame($marketer->id, (int) $campaign->created_by);
        $this->assertNull($campaign->approved_by);
    }

    public function test_marketer_cannot_review_commercial_template_without_preference_category(): void
    {
        Queue::fake();
        $template = EmailTemplate::factory()->create(['preference_category' => null]);
        $prospect = Prospect::factory()->create();

        $this->runAction($this->userWithRole('marketer'), [$prospect], [
            'description' => 'No category',
            'template_id' => $template->id,
        ])->assertNotified(__('prospects::resource.notifications.campaign_template_invalid.title'));

        Queue::assertNothingPushed();
        $this->assertSame(0, Campaign::count());
    }

    public function test_action_is_hidden_for_roles_that_cannot_create_campaigns(): void
    {
        Livewire::actingAs($this->userWithRole('viewer'))
            ->test(ManageProspects::class)
            ->assertTableBulkActionHidden('execute_campaign');
    }

    public function test_action_is_visible_for_marketer(): void
    {
        Livewire::actingAs($this->userWithRole('marketer'))
            ->test(ManageProspects::class)
            ->assertTableBulkActionVisible('execute_campaign');
    }

    #[DataProvider('creatorRoles')]
    public function test_all_unsubscribed_guard_creates_nothing(string $role): void
    {
        Queue::fake();
        $template  = EmailTemplate::factory()->create();
        $prospects = Prospect::factory()->count(2)->create(['unsubscribed_at' => now()]);

        $this->runAction($this->userWithRole($role), $prospects->all(), [
            'description' => 'Guard',
            'template_id' => $template->id,
        ])->assertNotified(__('prospects::resource.notifications.all_prospects_unsubscribed.title'));

        Queue::assertNothingPushed();
        $this->assertSame(0, Campaign::count());
    }

    #[DataProvider('creatorRoles')]
    public function test_no_emails_guard_creates_nothing(string $role): void
    {
        Queue::fake();
        $template = EmailTemplate::factory()->create();
        $prospect = Prospect::factory()->create();
        $prospect->locations()->delete();

        $this->runAction($this->userWithRole($role), [$prospect], [
            'description' => 'Guard',
            'template_id' => $template->id,
        ])->assertNotified(__('prospects::resource.notifications.no_emails_found.title'));

        Queue::assertNothingPushed();
        $this->assertSame(0, Campaign::count());
    }
}
