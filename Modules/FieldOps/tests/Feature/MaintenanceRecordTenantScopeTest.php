<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\Luminaire;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-497: MaintenanceRecordController::correctiveStats()/pendingClientReported()/
 * clientReportedStatistics() had no tenant scope at all — any authenticated non-client
 * actor (technician included, the one internal role without fieldops.view-all-clients)
 * saw global aggregates and cross-tenant PII (contact_person/contact_phone/
 * location_details). Fixed by applying FieldOpsTenantService::scopeForUser() to the
 * query before it is materialized — this file is the dedicated cross-tenant coverage
 * for that fix, exercised with two real clients (A/B), not just assertion-by-count.
 *
 * Note on CLA-375: scopeForUser() (list/aggregate endpoints) reads only active
 * fieldOpsClients memberships — it does NOT also consider a technician's currently
 * assigned work orders' client_id, unlike canView() (single-record detail access).
 * That is intentional and unchanged here: CLA-375 only widened canView() (opening a
 * specific record/equipment a technician is already assigned to), never scopeForUser()
 * (browsing/aggregate endpoints) — so an assigned-but-unlinked technician still gets an
 * empty list here, not a leak of just their assigned client's records either.
 */
class MaintenanceRecordTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($mock) => $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        foreach (['client', 'technician', 'project_manager', 'admin', 'some_future_internal_role'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_technician_scoped_to_client_a_only_counts_client_a_in_corrective_stats(): void
    {
        $clientA = FoClient::factory()->create();
        $clientB = FoClient::factory()->create();
        $type = \Modules\FieldOps\Models\FoMaintenanceType::factory()->corrective()->create();

        FoMaintenanceRecord::factory()->create(['client_id' => $clientA->id, 'fo_maintenance_type_id' => $type->id]);
        FoMaintenanceRecord::factory()->count(2)->create(['client_id' => $clientB->id, 'fo_maintenance_type_id' => $type->id]);

        $token = $this->technicianScopedTo($clientA);

        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-records/stats/corrective')
            ->assertOk()
            ->assertJsonPath('data.total_corrective', 1);
    }

    public function test_technician_scoped_to_client_a_never_sees_client_b_pii_in_pending_client_reported(): void
    {
        $clientA = FoClient::factory()->create();
        $clientB = FoClient::factory()->create();

        FoMaintenanceRecord::factory()->clientReported()->create([
            'client_id' => $clientA->id,
            'contact_person' => 'Alice Aardvark',
            'contact_phone' => '+32-111-000-111',
            'location_details' => 'Client A pitch, north stand',
        ]);
        FoMaintenanceRecord::factory()->clientReported()->create([
            'client_id' => $clientB->id,
            'contact_person' => 'Bob Badger',
            'contact_phone' => '+32-222-000-222',
            'location_details' => 'Client B pitch, east stand',
        ]);

        $token = $this->technicianScopedTo($clientA);

        $response = $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-records/client-reported/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.contact_person', 'Alice Aardvark')
            ->assertJsonPath('data.0.contact_phone', '+32-111-000-111')
            ->assertJsonPath('data.0.location_details', 'Client A pitch, north stand');

        $response->assertDontSee('Bob Badger')
            ->assertDontSee('+32-222-000-222')
            ->assertDontSee('Client B pitch, east stand');
    }

    public function test_technician_scoped_to_client_a_only_counts_client_a_in_statistics_and_by_client_never_names_client_b(): void
    {
        $clientA = FoClient::factory()->create();
        $clientB = FoClient::factory()->create();

        FoMaintenanceRecord::factory()->clientReported()->create(['client_id' => $clientA->id]);
        FoMaintenanceRecord::factory()->clientReported()->create(['client_id' => $clientA->id, 'problem_solved_at' => now()]);
        FoMaintenanceRecord::factory()->clientReported()->count(3)->create(['client_id' => $clientB->id]);

        $token = $this->technicianScopedTo($clientA);

        $response = $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-records/client-reported/statistics')
            ->assertOk()
            ->assertJsonPath('data.total_reported', 2)
            ->assertJsonPath('data.pending_count', 1)
            ->assertJsonPath('data.resolved_count', 1);

        $byClient = $response->json('data.by_client');
        $this->assertArrayHasKey((string) $clientA->id, $byClient);
        $this->assertArrayNotHasKey((string) $clientB->id, $byClient);
    }

    public function test_technician_without_any_client_membership_gets_empty_shape_not_an_error(): void
    {
        $type = \Modules\FieldOps\Models\FoMaintenanceType::factory()->corrective()->create();
        $client = FoClient::factory()->create();
        FoMaintenanceRecord::factory()->create(['client_id' => $client->id, 'fo_maintenance_type_id' => $type->id]);
        FoMaintenanceRecord::factory()->clientReported()->create(['client_id' => $client->id]);

        $user = UserFactory::new()->create();
        $user->assignRole('technician');
        $token = $user->createToken('field')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-records/stats/corrective')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => [
                    'total_corrective' => 0,
                    'emergency_count' => 0,
                    'avg_resolution_time' => null,
                    'total_downtime' => 0,
                    'unresolved_problems' => 0,
                ],
            ]);

        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-records/client-reported/pending')
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => []]);

        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-records/client-reported/statistics')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => [
                    'total_reported' => 0,
                    'pending_count' => 0,
                    'resolved_count' => 0,
                    'resolution_percentage' => 0,
                    'avg_resolution_time_hours' => 0,
                    'by_equipment_type' => [],
                    'by_client' => [],
                    'by_priority' => [],
                ],
            ]);
    }

    // Deliberately not named "technician" — scopeForUser()'s scoping is driven purely
    // by the absence of fieldops.view-all-clients, not by any specific role name, so
    // this proves the fix isn't accidentally coupled to "technician" being the only
    // role that can ever lack the broad permission.
    public function test_a_generic_internal_role_without_the_broad_permission_is_scoped_the_same_way(): void
    {
        $clientA = FoClient::factory()->create();
        $clientB = FoClient::factory()->create();
        $type = \Modules\FieldOps\Models\FoMaintenanceType::factory()->corrective()->create();
        FoMaintenanceRecord::factory()->create(['client_id' => $clientA->id, 'fo_maintenance_type_id' => $type->id]);
        FoMaintenanceRecord::factory()->create(['client_id' => $clientB->id, 'fo_maintenance_type_id' => $type->id]);

        $user = UserFactory::new()->create();
        $user->assignRole('some_future_internal_role');
        $user->fieldOpsClients()->attach($clientA->id, ['is_active' => true, 'can_view' => true]);
        $token = $user->createToken('internal')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-records/stats/corrective')
            ->assertOk()
            ->assertJsonPath('data.total_corrective', 1);
    }

    // CLA-497 (strengthened after review): a broad actor (fieldops.view-all-clients) must
    // see the FULL combined shape across all 3 endpoints, not just a plausible count — a
    // count-only assertion cannot distinguish "scoping was never applied" from "scoping
    // is applied but hasBroadAccess() correctly bypasses it". Every value below is
    // computed from deterministic fixtures (fixed offsets from a single Carbon anchor,
    // explicit downtime/priority/PII), so the full aggregated response is asserted
    // byte-for-byte via assertExactJson, and both A's and B's pending client-reported
    // records (with their distinct PII) are confirmed present.
    public function test_project_manager_with_broad_access_sees_the_full_combined_shape_across_all_three_endpoints(): void
    {
        $clientA = FoClient::factory()->create();
        $clientB = FoClient::factory()->create();
        $anchor = now();
        $correctiveType = \Modules\FieldOps\Models\FoMaintenanceType::factory()->corrective()->create();

        // corrective stats fixtures — 3 corrective records (2 for A, 1 for B).
        FoMaintenanceRecord::factory()->create([
            'client_id' => $clientA->id,
            'fo_maintenance_type_id' => $correctiveType->id,
            'is_emergency' => true,
            'problem_reported_at' => $anchor->copy()->subHours(13),
            'problem_solved_at' => $anchor->copy()->subHours(10), // resolved in 3h
            'downtime_hours' => 2.5,
        ]);
        FoMaintenanceRecord::factory()->create([
            'client_id' => $clientA->id,
            'fo_maintenance_type_id' => $correctiveType->id,
            'is_emergency' => false,
            'problem_reported_at' => $anchor->copy()->subHours(5),
            'problem_solved_at' => null, // unresolved
            'downtime_hours' => 1.5,
        ]);
        FoMaintenanceRecord::factory()->create([
            'client_id' => $clientB->id,
            'fo_maintenance_type_id' => $correctiveType->id,
            'is_emergency' => true,
            'problem_reported_at' => $anchor->copy()->subHours(20),
            'problem_solved_at' => $anchor->copy()->subHours(15), // resolved in 5h
            'downtime_hours' => 4.0,
        ]);

        // client-reported fixtures — 2 pending (1 A, 1 B) + 1 resolved (A), unique PII each.
        $pendingA = FoMaintenanceRecord::factory()->clientReported()->create([
            'client_id' => $clientA->id,
            'maintainable_type' => Luminaire::class,
            'contact_person' => 'Alice Aardvark',
            'contact_phone' => '+32-111-000-111',
            'location_details' => 'Client A pitch, north stand',
            'priority' => 'high',
            'problem_reported_at' => $anchor->copy()->subHours(2),
            'problem_solved_at' => null,
        ]);
        $resolvedA = FoMaintenanceRecord::factory()->clientReported()->create([
            'client_id' => $clientA->id,
            'maintainable_type' => Luminaire::class,
            'contact_person' => 'Carol Crow',
            'contact_phone' => '+32-333-000-333',
            'location_details' => 'Client A pitch, south stand',
            'priority' => 'medium',
            'problem_reported_at' => $anchor->copy()->subHours(6),
            'problem_solved_at' => $anchor->copy()->subHours(4), // resolved in 2h
        ]);
        $pendingB = FoMaintenanceRecord::factory()->clientReported()->create([
            'client_id' => $clientB->id,
            'maintainable_type' => Luminaire::class,
            'contact_person' => 'Bob Badger',
            'contact_phone' => '+32-222-000-222',
            'location_details' => 'Client B pitch, east stand',
            'priority' => 'low',
            'problem_reported_at' => $anchor->copy()->subHours(1),
            'problem_solved_at' => null,
        ]);

        $token = $this->broadActor('project_manager');

        // correctiveStats() — full aggregated response, A+B combined.
        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-records/stats/corrective')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => [
                    'total_corrective' => 3,
                    'emergency_count' => 2,
                    'avg_resolution_time' => 4, // avg(3h, 5h)
                    'total_downtime' => 8, // 2.5 + 1.5 + 4.0
                    'unresolved_problems' => 1,
                ],
            ]);

        // clientReportedStatistics() — full aggregated response, A+B combined,
        // by_client keyed by the real client ids (never a name, never anonymized).
        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-records/client-reported/statistics')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => [
                    'total_reported' => 3,
                    'pending_count' => 2,
                    'resolved_count' => 1,
                    'resolution_percentage' => 33.33,
                    'avg_resolution_time_hours' => 2, // only resolvedA has both timestamps
                    'by_equipment_type' => [Luminaire::class => 3],
                    'by_client' => [(string) $clientA->id => 2, (string) $clientB->id => 1],
                    'by_priority' => ['high' => 1, 'medium' => 1, 'low' => 1],
                ],
            ]);

        // pendingClientReported() — both A's and B's pending records present (never
        // excluded for a broad actor), each with its own PII intact and unmixed.
        // Ordered by problem_reported_at desc: B (anchor-1h) before A (anchor-2h).
        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-records/client-reported/pending')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $pendingB->id)
            ->assertJsonPath('data.0.contact_person', 'Bob Badger')
            ->assertJsonPath('data.0.contact_phone', '+32-222-000-222')
            ->assertJsonPath('data.0.location_details', 'Client B pitch, east stand')
            ->assertJsonPath('data.1.id', $pendingA->id)
            ->assertJsonPath('data.1.contact_person', 'Alice Aardvark')
            ->assertJsonPath('data.1.contact_phone', '+32-111-000-111')
            ->assertJsonPath('data.1.location_details', 'Client A pitch, north stand');

        // resolvedA is not "pending" — confirm it never leaks into this endpoint even
        // though it belongs to a client this broad actor can otherwise see everywhere.
        $this->assertNotNull($resolvedA->problem_solved_at);
    }

    // Representative regression for a second broad role — deliberately lighter
    // (count-based) than the project_manager test above, which already carries the
    // full deterministic shape coverage for all 3 endpoints; this just confirms the
    // same hasBroadAccess() bypass isn't accidentally coupled to one specific role.
    public function test_admin_with_broad_access_still_sees_every_client_combined(): void
    {
        $clientA = FoClient::factory()->create();
        $clientB = FoClient::factory()->create();

        FoMaintenanceRecord::factory()->clientReported()->create(['client_id' => $clientA->id]);
        FoMaintenanceRecord::factory()->clientReported()->create(['client_id' => $clientB->id]);

        $token = $this->broadActor('admin');

        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-records/client-reported/statistics')
            ->assertOk()
            ->assertJsonPath('data.total_reported', 2);
    }

    public function test_client_role_remains_blocked_from_all_three_routes(): void
    {
        $client = FoClient::factory()->create();
        $user = UserFactory::new()->create();
        $user->assignRole('client');
        $user->fieldOpsClients()->attach($client->id, ['is_active' => true, 'can_view' => true]);
        $token = $user->createToken('client-portal')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/fieldops/maintenance-records/stats/corrective')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/fieldops/maintenance-records/client-reported/pending')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/fieldops/maintenance-records/client-reported/statistics')->assertForbidden();
    }

    private function technicianScopedTo(FoClient $client): string
    {
        $user = UserFactory::new()->create();
        $user->assignRole('technician');
        $user->fieldOpsClients()->attach($client->id, ['is_active' => true, 'can_view' => true]);

        return $user->createToken('field')->plainTextToken;
    }

    private function broadActor(string $role): string
    {
        $user = UserFactory::new()->create();
        $user->assignRole($role);
        $user->givePermissionTo(Permission::findOrCreate('fieldops.view-all-clients', 'web'));

        return $user->createToken('broad')->plainTextToken;
    }
}
