<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use App\Filament\Clusters\Website\Resources\ConsultationRequestResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Services\ConsultationService;
use RuntimeException;
use Tests\TestCase;

/**
 * F4/CLA-477 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "Bandeja de leads aislada y flujo comercial" — the lead-inbox workflow
 * this ticket introduced on top of the pre-existing ConsultationRequest
 * CRM fields (status/priority/assigned_to/tags/internal_notes already
 * existed; this ticket replaced the status set, added a measurable first-
 * response SLA, and added the same-organization assignee guard).
 */
final class LeadInboxWorkflowTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    public function test_a_new_consultation_starts_in_the_new_status(): void
    {
        $consultation = app(ConsultationService::class)->createRequest([
            'name' => 'Jan Claesen',
            'email' => 'jan@example.test',
            'message' => 'Test.',
        ]);

        $this->assertSame(ConsultationRequest::STATUS_NEW, $consultation->status);
        $this->assertNull($consultation->firstResponseSlaMinutes());
    }

    public function test_update_status_stamps_first_response_only_the_first_time_it_leaves_new(): void
    {
        $consultation = ConsultationRequest::factory()->newLead()->create();
        $service = app(ConsultationService::class);

        $service->updateStatus($consultation, ConsultationRequest::STATUS_ASSIGNED);
        $consultation->refresh();

        $this->assertNotNull($consultation->first_response_at);
        $firstStamp = $consultation->first_response_at;
        $this->assertIsInt($consultation->firstResponseSlaMinutes());

        $service->updateStatus($consultation, ConsultationRequest::STATUS_IN_PROGRESS);
        $consultation->refresh();

        $this->assertTrue($consultation->first_response_at->equalTo($firstStamp), 'The SLA measures the first response only — a later status change must not re-stamp it.');
    }

    public function test_editing_via_the_filament_form_also_stamps_first_response(): void
    {
        $consultation = ConsultationRequest::factory()->newLead()->create();

        // Exercises ConsultationRequestObserver::saving() directly — the
        // real Eloquent ->save() path, distinct from updateStatus()'s
        // updateQuietly() (which bypasses observers entirely and stamps
        // inline instead, covered by the test above).
        $consultation->status = ConsultationRequest::STATUS_WAITING_CLIENT;
        $consultation->save();

        $consultation->refresh();
        $this->assertNotNull($consultation->first_response_at);
    }

    public function test_assigning_a_new_lead_auto_advances_it_to_assigned_and_stamps_first_response(): void
    {
        $consultation = ConsultationRequest::factory()->newLead()->create();
        $user = $this->claesenUser();

        // Real bug found via manual smoke testing, not by the narrower
        // version of this test that only checked status: the auto-transition
        // mutates $consultationRequest->status from inside the same
        // saving() call that the SLA-stamp isDirty('status') check reads —
        // ordering inside the observer matters, and this is the regression
        // test for it.
        $consultation->assigned_to = $user->id;
        $consultation->save();

        $fresh = $consultation->fresh();
        $this->assertSame(ConsultationRequest::STATUS_ASSIGNED, $fresh->status);
        $this->assertNotNull($fresh->first_response_at);
    }

    public function test_assigning_an_in_progress_lead_never_overrides_its_status(): void
    {
        $consultation = ConsultationRequest::factory()->create(['status' => ConsultationRequest::STATUS_IN_PROGRESS]);
        $user = $this->claesenUser();

        $consultation->assigned_to = $user->id;
        $consultation->save();

        $this->assertSame(ConsultationRequest::STATUS_IN_PROGRESS, $consultation->fresh()->status);
    }

    public function test_assignee_organization_guard_is_inert_with_enforcement_off(): void
    {
        $bertelsUser = $this->bertelsFixtureUser();
        $consultation = ConsultationRequest::factory()->newLead()->create();

        // Default in every environment today (D4) — assigning across a
        // fixture organization boundary must not throw while off.
        $consultation->assigned_to = $bertelsUser->id;
        $consultation->save();

        $this->assertSame($bertelsUser->id, $consultation->fresh()->assigned_to);
    }

    public function test_assignee_organization_guard_rejects_a_cross_organization_assignee_when_enforced(): void
    {
        $this->enableOrganizationEnforcement();
        $bertelsUser = $this->bertelsFixtureUser();
        $consultation = ConsultationRequest::factory()->newLead()->create(); // site defaults to Claesen

        $this->expectException(RuntimeException::class);

        $consultation->assigned_to = $bertelsUser->id;
        $consultation->save();
    }

    public function test_assignee_organization_guard_allows_a_same_organization_assignee_when_enforced(): void
    {
        $this->enableOrganizationEnforcement();
        $claesenUser = $this->claesenUser();
        $consultation = ConsultationRequest::factory()->newLead()->create();

        $consultation->assigned_to = $claesenUser->id;
        $consultation->save();

        $this->assertSame($claesenUser->id, $consultation->fresh()->assigned_to);
    }

    public function test_the_filament_resource_narrows_assignable_users_to_the_records_own_organization_when_enforced(): void
    {
        $this->enableOrganizationEnforcement();
        $claesenUser = $this->claesenUser();
        $bertelsUser = $this->bertelsFixtureUser();
        $consultation = ConsultationRequest::factory()->newLead()->create();

        $method = new \ReflectionMethod(ConsultationRequestResource::class, 'assignableUsersQuery');
        $method->setAccessible(true);
        $ids = $method->invoke(null, $consultation, User::query())->pluck('id');

        $this->assertTrue($ids->contains($claesenUser->id));
        $this->assertFalse($ids->contains($bertelsUser->id));
    }

    public function test_the_filament_resource_does_not_narrow_assignable_users_when_enforcement_is_off(): void
    {
        $claesenUser = $this->claesenUser();
        $bertelsUser = $this->bertelsFixtureUser();
        $consultation = ConsultationRequest::factory()->newLead()->create();

        $method = new \ReflectionMethod(ConsultationRequestResource::class, 'assignableUsersQuery');
        $method->setAccessible(true);
        $ids = $method->invoke(null, $consultation, User::query())->pluck('id');

        $this->assertTrue($ids->contains($claesenUser->id));
        $this->assertTrue($ids->contains($bertelsUser->id));
    }

    public function test_tags_round_trip_as_a_plain_array(): void
    {
        $consultation = ConsultationRequest::factory()->create(['tags' => ['vip', 'stadium']]);

        $this->assertSame(['vip', 'stadium'], $consultation->fresh()->tags);
    }

    public function test_a_bertels_fixture_sites_lead_is_never_assignable_to_a_claesen_user_when_enforced(): void
    {
        $this->enableOrganizationEnforcement();
        $bertelsSite = $this->bertelsFixtureSite();
        $claesenUser = $this->claesenUser();
        $consultation = ConsultationRequest::factory()->newLead()->create(['site_id' => $bertelsSite->id]);

        $this->expectException(RuntimeException::class);

        $consultation->assigned_to = $claesenUser->id;
        $consultation->save();
    }
}
