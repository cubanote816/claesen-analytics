<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Tests\TestCase;

/**
 * K2 — clients and projects (docs/BACKEND-API.md §4.3 and §4.7).
 *
 * The assertions check the *exact* key set of every payload, not just that some
 * keys are present: the office app is typed against this shape, so an extra or
 * renamed field is a break, not a detail.
 */
final class KnxProjectsAndClientsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The tenant has to exist before the fixture runs (KnxTenant refuses to
        // invent one), and it is the fixture that creates the office account:
        // seeding *after* creating our own person would delete it, because the
        // demo seed replaces the tenant's rows.
        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        $this->actingAs(
            User::query()->where('email', 'lien.smet@electrobertels.be')->sole(),
            'sanctum',
        );

    }

    public function test_the_endpoints_require_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/knx/projects')->assertUnauthorized();
        $this->getJson('/api/v1/knx/clients')->assertUnauthorized();
    }

    public function test_the_project_list_has_the_contract_shape_and_order(): void
    {
        $response = $this->getJson('/api/v1/knx/projects')->assertOk();

        $projects = $response->json();

        $this->assertSame(
            ['code', 'name', 'clientId', 'clientName', 'city', 'lead', 'devicesPlanned', 'devicesDone', 'photos', 'status', 'deadline'],
            array_keys($projects[0]),
        );

        $this->assertSame(
            ['C1618', '239870', '232146', '240512', '228104'],
            array_column($projects, 'code'),
        );

        $first = $projects[0];
        $this->assertSame('UV Campus · Gelijkvloers', $first['name']);
        $this->assertSame('UV Vastgoed', $first['clientName']);
        // `lead` is a short display name, not the stored full name.
        $this->assertSame('L. Smet', $first['lead']);
        $this->assertSame(24, $first['devicesPlanned']);
        $this->assertSame('busy', $first['status']);
        $this->assertSame('2026-10-10', $first['deadline']);
        $this->assertIsString($first['clientId']);
    }

    public function test_the_project_list_filters_by_status_and_search_term(): void
    {
        $this->getJson('/api/v1/knx/projects?status=busy')->assertOk()->assertJsonCount(2);
        $this->getJson('/api/v1/knx/projects?status=done')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/v1/knx/projects?status=all')->assertOk()->assertJsonCount(5);

        // q searches the code, the name, the city and the client's name.
        $this->getJson('/api/v1/knx/projects?q=C1618')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/v1/knx/projects?q=Hectaar')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/v1/knx/projects?q=Westerlo')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/v1/knx/projects?q=UV')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/v1/knx/projects?q=niets')->assertOk()->assertJsonCount(0);

        $this->getJson('/api/v1/knx/projects?status=onzin')
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_a_single_project_is_addressed_by_its_code(): void
    {
        $project = $this->getJson('/api/v1/knx/projects/C1618')->assertOk()->json();

        $this->assertSame('C1618', $project['code']);
        $this->assertSame('L. Smet', $project['lead']);

        // An unknown code is a 404 in the contract's envelope, not a 500.
        $this->getJson('/api/v1/knx/projects/NOPE')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found')
            ->assertJsonStructure(['message', 'code', 'errors']);
    }

    public function test_the_stats_count_the_conflicts_the_office_still_has_to_work(): void
    {
        $stats = $this->getJson('/api/v1/knx/projects/C1618/stats')->assertOk()->json();

        $this->assertSame(
            ['devicesPlanned', 'devicesDone', 'openConflicts', 'photos', 'deadline'],
            array_keys($stats),
        );

        $this->assertSame(24, $stats['devicesPlanned']);
        $this->assertSame(17, $stats['devicesDone']);
        // C1618 has k1 and k2 `open` and k5 `verified` → two still to work.
        $this->assertSame(2, $stats['openConflicts']);
        $this->assertSame('2026-10-10', $stats['deadline']);
    }

    public function test_the_client_list_has_the_contract_shape_and_filters(): void
    {
        $clients = $this->getJson('/api/v1/knx/clients')->assertOk()->json();

        $this->assertCount(5, $clients);
        $this->assertSame(
            ['id', 'name', 'city', 'contact', 'phone', 'email', 'address', 'vat'],
            array_keys($clients[0]),
        );
        $this->assertSame('UV Vastgoed', $clients[0]['name']);
        $this->assertSame('BE 0412.345.678', $clients[0]['vat']);

        $this->getJson('/api/v1/knx/clients?q=Hectaar')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/v1/knx/clients?q=Kempen')->assertOk()->assertJsonCount(1);
    }

    public function test_a_client_detail_carries_only_its_own_projects(): void
    {
        $clientId = $this->getJson('/api/v1/knx/clients?q=UV')->json()[0]['id'];

        $detail = $this->getJson('/api/v1/knx/clients/'.$clientId)->assertOk()->json();

        $this->assertSame('UV Vastgoed', $detail['name']);
        $this->assertSame(['C1618'], array_column($detail['projects'], 'code'));

        $this->getJson('/api/v1/knx/clients/999999')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }
}
