<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Policies\CampaignPolicy;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampaignWorkflowTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Status transitions (Campaign model)
    // -------------------------------------------------------------------------

    public function test_draft_can_transition_to_review(): void
    {
        $campaign = Campaign::factory()->create(['status' => CampaignStatus::DRAFT]);

        $campaign->transitionTo(CampaignStatus::REVIEW);

        $this->assertSame(CampaignStatus::REVIEW, $campaign->fresh()->status);
    }

    public function test_draft_cannot_transition_directly_to_sending(): void
    {
        $campaign = Campaign::factory()->create(['status' => CampaignStatus::DRAFT]);

        $this->expectException(\DomainException::class);

        $campaign->transitionTo(CampaignStatus::SENDING);
    }

    public function test_draft_cannot_transition_directly_to_approved(): void
    {
        $campaign = Campaign::factory()->create(['status' => CampaignStatus::DRAFT]);

        $this->expectException(\DomainException::class);

        $campaign->transitionTo(CampaignStatus::APPROVED);
    }

    public function test_review_can_be_approved(): void
    {
        $campaign = Campaign::factory()->inReview()->create();

        $campaign->transitionTo(CampaignStatus::APPROVED);

        $this->assertSame(CampaignStatus::APPROVED, $campaign->fresh()->status);
    }

    public function test_review_can_be_rejected_back_to_draft(): void
    {
        $campaign = Campaign::factory()->inReview()->create();

        $campaign->transitionTo(CampaignStatus::DRAFT);

        $this->assertSame(CampaignStatus::DRAFT, $campaign->fresh()->status);
    }

    public function test_approved_transitions_to_sending(): void
    {
        $campaign = Campaign::factory()->approved()->create();

        $campaign->transitionTo(CampaignStatus::SENDING);

        $this->assertSame(CampaignStatus::SENDING, $campaign->fresh()->status);
    }

    public function test_completed_is_terminal_no_transitions_allowed(): void
    {
        $campaign = Campaign::factory()->completed()->create();

        $this->expectException(\DomainException::class);

        $campaign->transitionTo(CampaignStatus::DRAFT);
    }

    public function test_approve_sets_approved_by_and_approved_at(): void
    {
        $user     = User::factory()->create();
        $campaign = Campaign::factory()->inReview()->create();

        $campaign->transitionTo(CampaignStatus::APPROVED, $user->id);

        $fresh = $campaign->fresh();
        $this->assertSame($user->id, $fresh->approved_by);
        $this->assertNotNull($fresh->approved_at);
    }

    // -------------------------------------------------------------------------
    // CampaignPolicy
    // -------------------------------------------------------------------------

    private function makeUser(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    public function test_viewer_cannot_create_campaign(): void
    {
        $viewer = $this->makeUser('viewer');
        $policy = new CampaignPolicy();

        $this->assertFalse($policy->create($viewer));
    }

    public function test_marketer_can_create_campaign(): void
    {
        $marketer = $this->makeUser('marketer');
        $policy   = new CampaignPolicy();

        $this->assertTrue($policy->create($marketer));
    }

    public function test_marketer_cannot_approve_campaign(): void
    {
        $marketer = $this->makeUser('marketer');
        $campaign = Campaign::factory()->inReview()->create();
        $policy   = new CampaignPolicy();

        $this->assertFalse($policy->approve($marketer, $campaign));
    }

    public function test_campaign_manager_can_approve_campaign_in_review(): void
    {
        $manager  = $this->makeUser('campaign_manager');
        $campaign = Campaign::factory()->inReview()->create();
        $policy   = new CampaignPolicy();

        $this->assertTrue($policy->approve($manager, $campaign));
    }

    public function test_admin_can_approve_campaign_in_review(): void
    {
        $admin    = $this->makeUser('admin');
        $campaign = Campaign::factory()->inReview()->create();
        $policy   = new CampaignPolicy();

        $this->assertTrue($policy->approve($admin, $campaign));
    }

    public function test_campaign_manager_cannot_approve_campaign_not_in_review(): void
    {
        $manager  = $this->makeUser('campaign_manager');
        $campaign = Campaign::factory()->create(['status' => CampaignStatus::DRAFT]);
        $policy   = new CampaignPolicy();

        $this->assertFalse($policy->approve($manager, $campaign));
    }

    public function test_marketer_can_only_update_own_draft_campaigns(): void
    {
        $marketer      = $this->makeUser('marketer');
        $ownCampaign   = Campaign::factory()->create(['status' => CampaignStatus::DRAFT, 'created_by' => $marketer->id]);
        $otherCampaign = Campaign::factory()->create(['status' => CampaignStatus::DRAFT]);
        $policy        = new CampaignPolicy();

        $this->assertTrue($policy->update($marketer, $ownCampaign));
        $this->assertFalse($policy->update($marketer, $otherCampaign));
    }

    public function test_no_one_can_update_a_non_draft_campaign_except_via_transition(): void
    {
        $admin    = $this->makeUser('admin');
        $campaign = Campaign::factory()->inReview()->create();
        $policy   = new CampaignPolicy();

        $this->assertFalse($policy->update($admin, $campaign));
    }

    public function test_only_admin_can_cancel_campaign(): void
    {
        $admin    = $this->makeUser('admin');
        $manager  = $this->makeUser('campaign_manager');
        $campaign = Campaign::factory()->inReview()->create();
        $policy   = new CampaignPolicy();

        $this->assertTrue($policy->cancel($admin, $campaign));
        $this->assertFalse($policy->cancel($manager, $campaign));
    }

    public function test_cannot_cancel_a_terminal_campaign(): void
    {
        $admin    = $this->makeUser('admin');
        $campaign = Campaign::factory()->completed()->create();
        $policy   = new CampaignPolicy();

        $this->assertFalse($policy->cancel($admin, $campaign));
    }

    public function test_can_be_approved_by_returns_false_for_marketer(): void
    {
        $marketer = $this->makeUser('marketer');
        $campaign = Campaign::factory()->inReview()->create();

        $this->assertFalse($campaign->canBeApprovedBy($marketer));
    }

    public function test_can_be_approved_by_returns_true_for_campaign_manager(): void
    {
        $manager  = $this->makeUser('campaign_manager');
        $campaign = Campaign::factory()->inReview()->create();

        $this->assertTrue($campaign->canBeApprovedBy($manager));
    }
}
