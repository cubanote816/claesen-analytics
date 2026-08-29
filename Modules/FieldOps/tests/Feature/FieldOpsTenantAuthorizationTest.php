<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Cafca\Models\Employee;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\ElectricalBoardType;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceRequest;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\FieldOps\Models\LuminaireType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\StructureType;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Models\TerrainType;
use Modules\FieldOps\Policies\FieldOpsInfrastructurePolicy;
use Modules\FieldOps\Policies\FieldOpsTenantPolicy;
use Modules\Intelligence\Services\ClaudeVisionService;
use Modules\Intelligence\Services\GeminiService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FieldOpsTenantAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['client', 'technician', 'project_manager', 'admin', 'super_admin', 'financial_manager', 'hr_manager', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_client_user_only_lists_linked_client_topology(): void
    {
        $a = $this->topology('Client A');
        $b = $this->topology('Client B');
        [$user, $token] = $this->clientUser($a['client']);

        $this->assertTrue($user->fieldOpsClients->contains($a['client']));

        $this->withToken($token)->getJson('/api/v1/fieldops/clients')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['client']->id);
        $this->withToken($token)->getJson('/api/v1/fieldops/complexes')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['complex']->id);
        $this->withToken($token)->getJson('/api/v1/fieldops/terrains')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['terrain']->id);
        $this->withToken($token)->getJson('/api/v1/fieldops/structures')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['structure']->id);
        $this->withToken($token)->getJson('/api/v1/fieldops/luminaire-frames')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['frame']->id);
        $this->withToken($token)->getJson('/api/v1/fieldops/electrical-boards')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['board']->id);
        $this->withToken($token)->getJson('/api/v1/fieldops/terrains/count')
            ->assertOk()->assertJsonPath('data.total', 1);

        $this->assertNotSame($a['client']->id, $b['client']->id);
    }

    public function test_client_user_cannot_access_another_clients_objects_or_history(): void
    {
        $a = $this->topology('Client A');
        $b = $this->topology('Client B');
        [, $token] = $this->clientUser($a['client']);
        $record = FoMaintenanceRecord::factory()->forMaintainable($b['luminaire'])->create([
            'client_id' => $b['client']->id,
        ]);
        $media = Media::query()->create([
            'model_type' => Complex::class,
            'model_id' => $b['complex']->id,
            'collection_name' => 'photos',
            'name' => 'private-site-photo',
            'file_name' => 'private-site-photo.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'local',
            'size' => 1,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        foreach ([
            "/api/v1/fieldops/clients/{$b['client']->id}",
            "/api/v1/fieldops/complexes/{$b['complex']->id}",
            "/api/v1/fieldops/terrains/{$b['terrain']->id}",
            "/api/v1/fieldops/structures/{$b['structure']->id}",
            "/api/v1/fieldops/luminaire-frames/{$b['frame']->id}",
            "/api/v1/fieldops/luminaires/{$b['luminaire']->id}",
            "/api/v1/fieldops/electrical-boards/{$b['board']->id}",
            "/api/v1/fieldops/maintenance-records/{$record->id}",
            "/api/v1/fieldops/media/{$media->id}",
        ] as $url) {
            $this->withToken($token)->getJson($url)->assertForbidden();
        }
    }

    public function test_client_role_is_read_only_and_cannot_access_internal_work_orders(): void
    {
        $a = $this->topology('Client A');
        [, $token] = $this->clientUser($a['client']);

        $this->withToken($token)
            ->patchJson("/api/v1/fieldops/complexes/{$a['complex']->id}", ['name' => 'Changed'])
            ->assertForbidden();
        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-work-orders/assigned')
            ->assertForbidden();

        $this->assertDatabaseHas('fo_complexes', ['id' => $a['complex']->id, 'name' => 'Client A site']);
    }

    public function test_client_user_can_mark_only_their_fieldops_notifications_as_read(): void
    {
        $client = FoClient::factory()->create();
        [$user, $token] = $this->clientUser($client);
        $firstNotification = (string) Str::uuid();
        $secondNotification = (string) Str::uuid();

        foreach ([$firstNotification, $secondNotification] as $notification) {
            $user->notifications()->create([
                'id' => $notification,
                'type' => 'fieldops-test',
                'data' => ['viewData' => ['module' => 'fieldops']],
            ]);
        }

        $this->withToken($token)
            ->postJson('/api/v1/fieldops/notifications/read-all')
            ->assertOk();

        $this->assertNotNull($user->notifications()->findOrFail($firstNotification)->read_at);

        $user->notifications()->whereKey($secondNotification)->update(['read_at' => null]);

        $this->withToken($token)
            ->postJson("/api/v1/fieldops/notifications/{$secondNotification}/read")
            ->assertOk();

        $this->assertNotNull($user->notifications()->findOrFail($secondNotification)->read_at);
    }

    public function test_inactive_or_non_viewable_membership_grants_no_access(): void
    {
        $a = $this->topology('Client A');
        [$user, $token] = $this->clientUser($a['client']);
        $user->fieldOpsClients()->updateExistingPivot($a['client']->id, ['can_view' => false]);

        $this->withToken($token)->getJson('/api/v1/fieldops/clients')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($token)->getJson("/api/v1/fieldops/complexes/{$a['complex']->id}")->assertForbidden();
    }

    public function test_ambiguous_cross_client_equipment_is_hidden_from_both_clients(): void
    {
        $a = $this->topology('Client A');
        $b = $this->topology('Client B');
        $a['board']->complexes()->attach($b['complex']);
        [, $token] = $this->clientUser($a['client']);

        $this->withToken($token)->getJson('/api/v1/fieldops/electrical-boards')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($token)->getJson("/api/v1/fieldops/electrical-boards/{$a['board']->id}")
            ->assertForbidden();
    }

    // CLA-364: broad FieldOps access is the exception (fieldops.view-all-clients),
    // not the default for any non-client role. Any role without that permission is
    // scoped exactly like a client, via the same fieldOpsClients() assignment
    // (managed today from Users > fieldOpsClients in Filament — no separate
    // assignment mechanism was built for this). technician stays without the
    // permission in the real seeder; project_manager was moved into the broad
    // group afterwards (RolesAndPermissionsSeeder) so PMs see every client by
    // default — the test below exercises the underlying mechanism against a
    // project_manager role built in isolation (setUp() above, not the seeder), to
    // keep proving the scoping itself still works for a role that doesn't have
    // the permission, independent of which real roles carry it today.
    public function test_technician_without_a_client_assignment_sees_nothing(): void
    {
        $this->topology('Client A');
        [, $token] = $this->internalUser('technician');

        $this->withToken($token)->getJson('/api/v1/fieldops/complexes')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_technician_with_a_client_assignment_is_scoped_like_a_client(): void
    {
        $a = $this->topology('Client A');
        $b = $this->topology('Client B');
        [, $token] = $this->internalUser('technician', $a['client']);

        $this->withToken($token)->getJson('/api/v1/fieldops/complexes')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['complex']->id);

        $this->assertNotSame($a['client']->id, $b['client']->id);
    }

    public function test_project_manager_without_the_broad_permission_is_also_scoped(): void
    {
        $this->topology('Client A');
        [, $token] = $this->internalUser('project_manager');

        $this->withToken($token)->getJson('/api/v1/fieldops/complexes')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_admin_with_the_broad_permission_sees_every_client(): void
    {
        $a = $this->topology('Client A');
        $b = $this->topology('Client B');
        [, $token] = $this->internalUser('admin', broadAccess: true);

        $this->withToken($token)->getJson('/api/v1/fieldops/complexes')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->assertNotSame($a['client']->id, $b['client']->id);
    }

    // CLA-369: work orders are the one model where the Client Portal rule
    // (isClientUser — never see work orders) and the general scoping rule
    // (hasBroadAccess — everyone else, scoped to assigned clients) both apply
    // to the same model, so it's worth its own pair of tests distinct from the
    // generic scoping coverage above.
    public function test_technician_can_view_a_work_order_for_their_assigned_client(): void
    {
        $a = $this->topology('Client A');
        // show() also gates on authorizeWorkerOrPlanner() (admin, or the
        // order's own assigned employee) independent of tenant scope — give
        // this technician a matching employee_id so that check isn't what's
        // actually being exercised here.
        $employee = Employee::create(['id' => 'TECH-VIEW', 'name' => 'Technician', 'fl_active' => true]);
        $user = UserFactory::new()->create(['employee_id' => $employee->id]);
        $user->assignRole('technician');
        $user->fieldOpsClients()->attach($a['client']->id, ['is_active' => true, 'can_view' => true]);
        $token = $user->createToken('field')->plainTextToken;

        $order = FoMaintenanceWorkOrder::factory()
            ->forMaintainable($a['luminaire'])
            ->create(['client_id' => $a['client']->id, 'assigned_employee_id' => $employee->id]);

        $this->withToken($token)->getJson("/api/v1/fieldops/maintenance-work-orders/{$order->id}")
            ->assertOk()->assertJsonPath('data.id', $order->id);
    }

    public function test_technician_cannot_view_a_work_order_for_another_client(): void
    {
        $a = $this->topology('Client A');
        $b = $this->topology('Client B');
        $employee = Employee::create(['id' => 'TECH-CROSS', 'name' => 'Technician', 'fl_active' => true]);
        $otherEmployee = Employee::create(['id' => 'TECH-CROSS-OTHER', 'name' => 'Other Technician', 'fl_active' => true]);
        $user = UserFactory::new()->create(['employee_id' => $employee->id]);
        $user->assignRole('technician');
        $user->fieldOpsClients()->attach($a['client']->id, ['is_active' => true, 'can_view' => true]);
        $token = $user->createToken('field')->plainTextToken;

        // CLA-375: assigned to a different employee, so the assignment-based
        // widening below doesn't apply here either — this stays forbidden on
        // tenant scope alone. (Before CLA-375 this test assigned the order to
        // $employee itself and still expected 403 — that was the bug: an
        // assigned technician couldn't view their own order. See the two
        // CLA-375 tests below for that corrected case.)
        $order = FoMaintenanceWorkOrder::factory()
            ->forMaintainable($b['luminaire'])
            ->create(['client_id' => $b['client']->id, 'assigned_employee_id' => $otherEmployee->id]);

        $this->withToken($token)->getJson("/api/v1/fieldops/maintenance-work-orders/{$order->id}")
            ->assertForbidden();
    }

    // CLA-375: assigning a work order never grants fieldOpsClients scope —
    // without this, a technician who legitimately gets assigned work sees it
    // in their queue (assigned() filters by assigned_employee_id only) but
    // gets 403 on the work order itself and every linked equipment page.
    public function test_technician_can_view_their_assigned_work_order_and_its_equipment_without_a_client_assignment(): void
    {
        $a = $this->topology('Client A');
        $employee = Employee::create(['id' => 'TECH-ASSIGNED-NO-SCOPE', 'name' => 'Technician', 'fl_active' => true]);
        $user = UserFactory::new()->create(['employee_id' => $employee->id]);
        $user->assignRole('technician');
        $token = $user->createToken('field')->plainTextToken;

        $order = FoMaintenanceWorkOrder::factory()
            ->forMaintainable($a['luminaire'])
            ->create(['client_id' => $a['client']->id, 'assigned_employee_id' => $employee->id]);

        $this->assertTrue($user->fieldOpsClients->isEmpty());

        $this->withToken($token)->getJson("/api/v1/fieldops/maintenance-work-orders/{$order->id}")
            ->assertOk()->assertJsonPath('data.id', $order->id);
        $this->withToken($token)->getJson("/api/v1/fieldops/complexes/{$a['complex']->id}")
            ->assertOk()->assertJsonPath('data.id', $a['complex']->id);

        // The widened scope is for detail access only — listing endpoints stay
        // untouched (same behavior as test_technician_without_a_client_assignment_sees_nothing).
        $this->withToken($token)->getJson('/api/v1/fieldops/complexes')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_technician_with_an_assigned_work_order_still_cannot_view_unrelated_clients_equipment(): void
    {
        $a = $this->topology('Client A');
        $b = $this->topology('Client B');
        $employee = Employee::create(['id' => 'TECH-ASSIGNED-CROSS', 'name' => 'Technician', 'fl_active' => true]);
        $user = UserFactory::new()->create(['employee_id' => $employee->id]);
        $user->assignRole('technician');
        $token = $user->createToken('field')->plainTextToken;

        FoMaintenanceWorkOrder::factory()
            ->forMaintainable($a['luminaire'])
            ->create(['client_id' => $a['client']->id, 'assigned_employee_id' => $employee->id]);

        $this->withToken($token)->getJson("/api/v1/fieldops/complexes/{$b['complex']->id}")
            ->assertForbidden();
    }

    // ============================================================
    // CLA-496: capability matrix — create/update/delete separated from view scope.
    // Create: 5 resources (Complex has no POST route — bridge-only, CLA-226/227).
    // Update/delete: 6 resources, Complex included.
    // ============================================================

    public function test_broad_access_roles_without_write_permission_get_403_on_writes(): void
    {
        foreach (['financial_manager', 'hr_manager', 'viewer'] as $role) {
            $a = $this->topology("Broad {$role}");
            [, $token] = $this->internalUser($role, broadAccess: true);

            // create (5 resources, no Complex — it has no POST route)
            $this->withToken($token)->postJson('/api/v1/fieldops/terrains', $this->terrainPayload($a['complex']->id))
                ->assertForbidden();
            $this->withToken($token)->postJson('/api/v1/fieldops/structures', $this->structurePayload($a['terrain']->id))
                ->assertForbidden();
            $this->withToken($token)->postJson('/api/v1/fieldops/luminaire-frames', $this->luminaireFramePayload($a['structure']->id))
                ->assertForbidden();
            $this->withToken($token)->postJson('/api/v1/fieldops/luminaires', $this->luminairePayload($a['frame']->id))
                ->assertForbidden();
            $this->withToken($token)->postJson('/api/v1/fieldops/electrical-boards', $this->electricalBoardPayload())
                ->assertForbidden();

            // update (6 resources, Complex included)
            $this->withToken($token)->patchJson("/api/v1/fieldops/complexes/{$a['complex']->id}", ['lat' => 10.0])->assertForbidden();
            $this->withToken($token)->patchJson("/api/v1/fieldops/terrains/{$a['terrain']->id}", ['lat' => 10.0])->assertForbidden();
            $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$a['structure']->id}", ['height' => 5])->assertForbidden();
            $this->withToken($token)->patchJson("/api/v1/fieldops/luminaire-frames/{$a['frame']->id}", ['luminaire_frame_type_id' => LuminaireFrameType::factory()->create()->id])->assertForbidden();
            $this->withToken($token)->patchJson("/api/v1/fieldops/luminaires/{$a['luminaire']->id}", ['scale_x' => 2])->assertForbidden();
            $this->withToken($token)->patchJson("/api/v1/fieldops/electrical-boards/{$a['board']->id}", ['lat' => 10.0])->assertForbidden();

            // delete (6 resources, Complex included)
            $this->withToken($token)->deleteJson("/api/v1/fieldops/complexes/{$a['complex']->id}")->assertForbidden();
            $this->withToken($token)->deleteJson("/api/v1/fieldops/terrains/{$a['terrain']->id}")->assertForbidden();
            $this->withToken($token)->deleteJson("/api/v1/fieldops/structures/{$a['structure']->id}")->assertForbidden();
            $this->withToken($token)->deleteJson("/api/v1/fieldops/luminaire-frames/{$a['frame']->id}")->assertForbidden();
            $this->withToken($token)->deleteJson("/api/v1/fieldops/luminaires/{$a['luminaire']->id}")->assertForbidden();
            $this->withToken($token)->deleteJson("/api/v1/fieldops/electrical-boards/{$a['board']->id}")->assertForbidden();
        }
    }

    public function test_admin_and_super_admin_can_create_update_and_delete_infrastructure(): void
    {
        foreach (['admin', 'super_admin'] as $role) {
            $a = $this->topology("Full access {$role}");
            [, $token] = $this->internalUser($role, broadAccess: true, permissions: [
                'fieldops.create', 'fieldops.update', 'fieldops.delete-infrastructure',
            ]);

            $this->withToken($token)->postJson('/api/v1/fieldops/terrains', $this->terrainPayload($a['complex']->id))
                ->assertCreated();
            $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$a['structure']->id}", ['height' => 5])
                ->assertOk();
            $this->withToken($token)->deleteJson("/api/v1/fieldops/electrical-boards/{$a['board']->id}")
                ->assertNoContent();
        }
    }

    public function test_project_manager_can_create_and_update_infrastructure_but_not_delete(): void
    {
        $a = $this->topology('PM writable');
        [, $token] = $this->internalUser('project_manager', broadAccess: true, permissions: [
            'fieldops.create', 'fieldops.update',
        ]);

        $this->withToken($token)->postJson('/api/v1/fieldops/terrains', $this->terrainPayload($a['complex']->id))
            ->assertCreated();
        $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$a['structure']->id}", ['height' => 5])
            ->assertOk();
        $this->withToken($token)->deleteJson("/api/v1/fieldops/electrical-boards/{$a['board']->id}")
            ->assertForbidden();
        $this->withToken($token)->deleteJson("/api/v1/fieldops/luminaires/{$a['luminaire']->id}")
            ->assertForbidden();
    }

    public function test_technician_can_update_within_scope_but_not_cross_tenant_and_never_delete(): void
    {
        $a = $this->topology('Tech scoped A');
        $b = $this->topology('Tech scoped B');
        [, $token] = $this->internalUser('technician', $a['client'], permissions: ['fieldops.create', 'fieldops.update']);

        // within scope: create/update succeed
        $this->withToken($token)->postJson('/api/v1/fieldops/terrains', $this->terrainPayload($a['complex']->id))
            ->assertCreated();
        $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$a['structure']->id}", ['height' => 5])
            ->assertOk();

        // cross-tenant: update on Client B's equipment is forbidden by canView() inside update()
        $this->withToken($token)->patchJson("/api/v1/fieldops/structures/{$b['structure']->id}", ['height' => 5])
            ->assertForbidden();

        // delete: never, even within own scope, regardless of having fieldops.update
        $this->withToken($token)->deleteJson("/api/v1/fieldops/luminaires/{$a['luminaire']->id}")
            ->assertForbidden();
        $this->withToken($token)->deleteJson("/api/v1/fieldops/electrical-boards/{$a['board']->id}")
            ->assertForbidden();
    }

    public function test_client_role_still_cannot_write_infrastructure(): void
    {
        $a = $this->topology('Client writes');
        [, $token] = $this->clientUser($a['client']);

        $this->withToken($token)->postJson('/api/v1/fieldops/terrains', $this->terrainPayload($a['complex']->id))
            ->assertForbidden();
        $this->withToken($token)->deleteJson("/api/v1/fieldops/structures/{$a['structure']->id}")
            ->assertForbidden();
    }

    // Regression: maintenance work order/request mutation routes must keep exactly
    // the 'view' authorization they had before CLA-496 — they are POST/PATCH on
    // models outside INFRASTRUCTURE_MODELS, so the middleware's dispatch must never
    // touch them.
    public function test_maintenance_work_order_actions_keep_their_pre_cla496_authorization(): void
    {
        $a = $this->topology('Maintenance regression');
        $employee = Employee::create(['id' => 'TECH-CLA496-REG', 'name' => 'Technician', 'fl_active' => true]);
        $user = UserFactory::new()->create(['employee_id' => $employee->id]);
        $user->assignRole('technician');
        $token = $user->createToken('field')->plainTextToken;

        $order = FoMaintenanceWorkOrder::factory()
            ->forMaintainable($a['luminaire'])
            ->create(['client_id' => $a['client']->id, 'assigned_employee_id' => $employee->id, 'status' => 'assigned']);

        // Same behavior as before CLA-496: the assigned technician can reach the
        // work order (canView() widened by assignedWorkOrderClientIds(), CLA-375),
        // start() only requires 'view' — no new 'update'/'ai' ability was added here.
        $this->withToken($token)->postJson("/api/v1/fieldops/maintenance-work-orders/{$order->id}/start")
            ->assertOk();
    }

    public function test_maintenance_request_respond_patch_keeps_view_authorization_not_update(): void
    {
        // FoMaintenanceRequest is NOT in INFRASTRUCTURE_MODELS — a PATCH on it must
        // stay on 'view', not be silently upgraded to 'update' by the new dispatch.
        // No factory exists for this model (see MaintenanceRequestTest for the same
        // pattern) — created directly via query()->create().
        $client = FoClient::factory()->create();
        $board = ElectricalBoard::factory()->create();
        $request = FoMaintenanceRequest::query()->create([
            'client_id' => $client->id,
            'status' => \Modules\FieldOps\Enums\MaintenanceRequestStatus::RECEIVED,
            'description' => 'CLA-496 regression check',
            'maintainable_type' => ElectricalBoard::class,
            'maintainable_id' => $board->id,
        ]);
        [, $token] = $this->internalUser('admin', broadAccess: true);

        // This admin has fieldops.view-all-clients but deliberately NOT
        // fieldops.update — a 200 here only proves the request went through 'view'
        // (which hasBroadAccess() satisfies), not 'update' (which it would fail).
        $response = $this->withToken($token)->patchJson(
            "/api/v1/fieldops/maintenance-requests/{$request->id}/respond",
            ['public_response' => 'Acknowledged'],
        );

        $response->assertOk();
    }

    public function test_infrastructure_models_and_maintenance_models_resolve_to_different_policies(): void
    {
        $this->assertInstanceOf(FieldOpsInfrastructurePolicy::class, Gate::getPolicyFor(Complex::class));
        $this->assertInstanceOf(FieldOpsInfrastructurePolicy::class, Gate::getPolicyFor(Terrain::class));
        $this->assertInstanceOf(FieldOpsInfrastructurePolicy::class, Gate::getPolicyFor(Structure::class));
        $this->assertInstanceOf(FieldOpsInfrastructurePolicy::class, Gate::getPolicyFor(LuminaireFrame::class));
        $this->assertInstanceOf(FieldOpsInfrastructurePolicy::class, Gate::getPolicyFor(Luminaire::class));
        $this->assertInstanceOf(FieldOpsInfrastructurePolicy::class, Gate::getPolicyFor(ElectricalBoard::class));

        $this->assertInstanceOf(FieldOpsTenantPolicy::class, Gate::getPolicyFor(FoClient::class));
        $this->assertInstanceOf(FieldOpsTenantPolicy::class, Gate::getPolicyFor(FoMaintenanceRecord::class));
        $this->assertInstanceOf(FieldOpsTenantPolicy::class, Gate::getPolicyFor(FoMaintenanceWorkOrder::class));
        $this->assertInstanceOf(FieldOpsTenantPolicy::class, Gate::getPolicyFor(FoMaintenanceRequest::class));
    }

    // fieldops.media/fieldops.ai are created as foundation by CLA-496 but are
    // deliberately not enforced anywhere yet — CLA-498 (media) and CLA-502 (ai) are
    // the tickets that will actually gate on them. These two tests pin today's
    // (pre-CLA-498/CLA-502) behavior so that whoever implements those tickets sees
    // this assertion flip and knows to update/remove it, instead of it silently
    // passing for the wrong reason.
    public function test_media_upload_is_not_yet_gated_by_fieldops_media_permission_pending_cla498(): void
    {
        $a = $this->topology('Media inert CLA-498');
        // technician scoped to their own client, WITHOUT fieldops.media granted.
        [, $token] = $this->internalUser('technician', $a['client']);

        $response = $this->withToken($token)->postJson(
            "/api/v1/fieldops/complexes/{$a['complex']->id}/media",
            ['collection' => 'photos', 'file' => \Illuminate\Http\UploadedFile::fake()->image('site.jpg')],
        );

        $response->assertStatus(201); // pending CLA-498: will require fieldops.media
    }

    public function test_vision_endpoint_is_not_yet_gated_by_fieldops_ai_permission_pending_cla502(): void
    {
        $a = $this->topology('Vision inert CLA-502');
        // technician scoped to their own client, WITHOUT fieldops.ai granted.
        [, $token] = $this->internalUser('technician', $a['client']);

        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        $this->mock(ClaudeVisionService::class, function ($mock): void {
            $mock->shouldReceive('identifyLuminaires')->andReturn(['status' => 'unknown', 'candidates' => []]);
        });

        $response = $this->withToken($token)->postJson(
            "/api/v1/fieldops/luminaire-frames/{$a['frame']->id}/vision-suggestions",
            ['photo' => \Illuminate\Http\UploadedFile::fake()->image('frame.jpg')],
        );

        $response->assertStatus(200); // pending CLA-502: will require fieldops.ai
    }

    private function terrainPayload(int $complexId): array
    {
        return [
            'complex_id' => $complexId,
            'terrain_type_id' => TerrainType::factory()->create()->id,
        ];
    }

    private function structurePayload(int $terrainId): array
    {
        return [
            'structure_type_id' => StructureType::factory()->create()->id,
            'terrain_ids' => [$terrainId],
        ];
    }

    private function luminaireFramePayload(int $structureId): array
    {
        return [
            'luminaire_frame_type_id' => LuminaireFrameType::factory()->create()->id,
            'structure_ids' => [$structureId],
        ];
    }

    private function luminairePayload(int $frameId): array
    {
        $type = LuminaireType::factory()->create();

        return [
            'luminaire_frame_id' => $frameId,
            'luminaire_type_id' => $type->id,
            'luminaire_subgroup_id' => $type->luminaire_subgroup_id,
        ];
    }

    private function electricalBoardPayload(): array
    {
        return [
            'electrical_board_type_id' => ElectricalBoardType::factory()->create()->id,
        ];
    }

    private function internalUser(string $role, ?FoClient $client = null, bool $broadAccess = false, array $permissions = []): array
    {
        $user = UserFactory::new()->create();
        $user->assignRole($role);

        if ($broadAccess) {
            $user->givePermissionTo(Permission::findOrCreate('fieldops.view-all-clients', 'web'));
        }

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        if ($client) {
            $user->fieldOpsClients()->attach($client->id, [
                'is_active' => true,
                'can_view' => true,
                'can_report' => true,
            ]);
        }

        return [$user, $user->createToken('internal')->plainTextToken];
    }

    private function clientUser(FoClient $client): array
    {
        $user = UserFactory::new()->create();
        $user->assignRole('client');
        $user->fieldOpsClients()->attach($client->id, [
            'is_active' => true,
            'can_view' => true,
            'can_report' => true,
        ]);

        return [$user, $user->createToken('client-portal')->plainTextToken];
    }

    private function topology(string $name): array
    {
        $client = FoClient::factory()->create(['name' => $name]);
        $complex = Complex::factory()->create(['client_id' => $client->id, 'name' => "{$name} site"]);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain);
        $frame = LuminaireFrame::factory()->create();
        $frame->structures()->attach($structure);
        $luminaire = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id]);
        $board = ElectricalBoard::factory()->create();
        $board->complexes()->attach($complex);

        return compact('client', 'complex', 'terrain', 'structure', 'frame', 'luminaire', 'board');
    }
}
