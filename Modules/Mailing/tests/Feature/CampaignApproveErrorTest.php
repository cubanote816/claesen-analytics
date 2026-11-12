<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\TemplateCategory;
use Modules\Mailing\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use Modules\Mailing\Filament\Resources\CampaignResource\Pages\ViewCampaign;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\EmailTemplate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-669: when the workflow refuses an approval (DomainException from
 * Campaign::transitionTo), the Approve action must show the reason instead
 * of failing the request with a 500, and the campaign must stay in review.
 */
class CampaignApproveErrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    private function inReviewWithUncategorisedCommercialTemplate(): Campaign
    {
        $template = EmailTemplate::factory()->create([
            'name'                => '1. Eerste Kennismaking (Audit)',
            'category'            => TemplateCategory::COMMERCIAL,
            'preference_category' => null,
        ]);

        return Campaign::factory()->inReview()->create(['template_id' => $template->id]);
    }

    private function assertStillInReview(Campaign $campaign): void
    {
        $fresh = $campaign->fresh();

        $this->assertSame(CampaignStatus::REVIEW, $fresh->status);
        $this->assertNull($fresh->approved_by);
        $this->assertNull($fresh->approved_at);
        Queue::assertNothingPushed();
    }

    public function test_view_page_shows_the_refusal_instead_of_a_server_error(): void
    {
        $campaign = $this->inReviewWithUncategorisedCommercialTemplate();

        Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
            ->callAction('approve')
            ->assertNotified(__('mailing::resource.notifications.approve_failed'));

        $this->assertStillInReview($campaign);
    }

    public function test_campaign_list_shows_the_refusal_instead_of_a_server_error(): void
    {
        $campaign = $this->inReviewWithUncategorisedCommercialTemplate();

        Livewire::test(ListCampaigns::class)
            ->callTableAction('approve', $campaign)
            ->assertNotified(__('mailing::resource.notifications.approve_failed'));

        $this->assertStillInReview($campaign);
    }

    public function test_campaign_without_template_shows_the_refusal(): void
    {
        $campaign = Campaign::factory()->inReview()->create(['template_id' => null]);

        Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
            ->callAction('approve')
            ->assertNotified(__('mailing::resource.notifications.approve_failed'));

        $this->assertStillInReview($campaign);
    }

    public function test_valid_campaign_is_still_approved(): void
    {
        $template = EmailTemplate::factory()->asOffers()->create();
        $campaign = Campaign::factory()->inReview()->create(['template_id' => $template->id]);

        Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
            ->callAction('approve')
            ->assertNotified(__('mailing::resource.notifications.approved'));

        $fresh = $campaign->fresh();
        $this->assertSame(CampaignStatus::APPROVED, $fresh->status);
        $this->assertSame(auth()->id(), $fresh->approved_by);
        Queue::assertNothingPushed();
    }
}
