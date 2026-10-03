<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxClient;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;
use Modules\Knx\Support\KnxTenant;
use Tests\TestCase;

/**
 * K2 — the office's two write routes: `POST /clients` and `POST /projects`.
 *
 * Until now the office could read everything and change nothing it owns; the
 * front end had no way to create the project the field app consumes. The
 * assertions check the same exact key sets as the read tests (KnxProjectsAndClientsTest),
 * because the response of a create is the shape the office app already parses.
 */
final class KnxOfficeWritesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        $this->actingAs(
            User::query()->where('email', 'lien.smet@electrobertels.be')->sole(),
            'sanctum',
        );
    }

    private function uvVastgoedId(): int
    {
        return KnxClient::query()->where('name', 'UV Vastgoed')->sole()->id;
    }

    public function test_an_office_session_can_create_a_client(): void
    {
        $response = $this->postJson('/api/v1/knx/clients', [
            'name' => 'Testklant SMOKE',
            'city' => 'Leuven',
            'vat' => 'BE 0412.000.001',
        ])->assertCreated();

        // The same shape the client read returns — the front parses one Client.
        $this->assertSame(
            ['id', 'name', 'city', 'contact', 'phone', 'email', 'address', 'vat'],
            array_keys($response->json()),
        );
        $this->assertSame('Testklant SMOKE', $response->json('name'));

        $client = KnxClient::query()->where('name', 'Testklant SMOKE')->sole();
        $this->assertSame(KnxTenant::organizationId(), $client->organization_id);

        // Only `name` is required; the rest may stay empty.
        $this->postJson('/api/v1/knx/clients', ['name' => 'Minimaal Testklant'])->assertCreated();
    }

    public function test_an_office_session_can_create_a_project_with_its_rooms(): void
    {
        $leadId = KnxEmployee::query()->where('name', 'Lien Smet')->sole()->id;

        $response = $this->postJson('/api/v1/knx/projects', [
            'clientId' => $this->uvVastgoedId(),
            'code' => 'SMOKE-1',
            'name' => 'Testproject SMOKE',
            'city' => 'Leuven',
            'leadEmployeeId' => $leadId,
            'deadline' => '2026-12-31',
            'rooms' => [
                ['name' => 'Inkomhal'],
                ['name' => 'Bureau', 'floor' => '1e verdieping'],
            ],
        ])->assertCreated();

        // The same shape the project read returns.
        $this->assertSame(
            ['code', 'name', 'clientId', 'clientName', 'city', 'lead', 'devicesPlanned', 'devicesDone', 'photos', 'status', 'deadline'],
            array_keys($response->json()),
        );
        $this->assertSame('SMOKE-1', $response->json('code'));
        $this->assertSame('UV Vastgoed', $response->json('clientName'));
        $this->assertSame('L. Smet', $response->json('lead'));
        $this->assertSame('2026-12-31', $response->json('deadline'));
        // Defaults the caller never sent: a new project starts empty.
        $this->assertSame('planned', $response->json('status'));
        $this->assertSame(0, $response->json('devicesPlanned'));
        $this->assertSame(0, $response->json('devicesDone'));
        $this->assertSame(0, $response->json('photos'));

        $project = KnxProject::query()->where('code', 'SMOKE-1')->sole();
        $this->assertSame(KnxTenant::organizationId(), $project->organization_id);
        $this->assertSame(2, KnxProjectRoom::query()->where('project_id', $project->id)->count());
        $this->assertSame('1e verdieping', KnxProjectRoom::query()->where('project_id', $project->id)->where('name', 'Bureau')->sole()->floor);

        // The field app reads the project back through the existing route, so the
        // created row must be visible there too.
        $this->getJson('/api/v1/knx/projects/SMOKE-1')->assertOk()->assertJsonPath('code', 'SMOKE-1');

        // Rooms are optional: a project without the key still creates.
        $this->postJson('/api/v1/knx/projects', [
            'clientId' => $this->uvVastgoedId(),
            'code' => 'SMOKE-2',
            'name' => 'Testproject SMOKE zonder ruimtes',
        ])->assertCreated();

        // The caller cannot choose the tenant, not even by naming another
        // organization's id: the tenant always comes from the session.
        $foreignOrganizationId = Organization::query()
            ->whereKeyNot(KnxTenant::organizationId())
            ->value('id');

        $this->postJson('/api/v1/knx/projects', [
            'organization_id' => $foreignOrganizationId,
            'clientId' => $this->uvVastgoedId(),
            'code' => 'SMOKE-3',
            'name' => 'Testproject SMOKE met vreemde tenant',
        ])->assertCreated();

        $this->assertSame(
            KnxTenant::organizationId(),
            KnxProject::query()->where('code', 'SMOKE-3')->sole()->organization_id,
        );
    }

    public function test_a_duplicate_project_code_is_a_field_error_and_not_a_500(): void
    {
        // `C1618` is the fixture's own first project code.
        $response = $this->postJson('/api/v1/knx/projects', [
            'clientId' => $this->uvVastgoedId(),
            'code' => 'C1618',
            'name' => 'Testproject SMOKE dubbel nummer',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['code']]);

        $this->assertSame(__('knx::projects.duplicate_code'), $response->json('errors.code.0'));
    }

    public function test_an_unknown_client_or_lead_is_a_field_error(): void
    {
        $this->postJson('/api/v1/knx/projects', [
            'clientId' => 999999,
            'code' => 'SMOKE-X1',
            'name' => 'Testproject SMOKE onbekende klant',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['clientId']]);

        $this->postJson('/api/v1/knx/projects', [
            'clientId' => $this->uvVastgoedId(),
            'code' => 'SMOKE-X2',
            'name' => 'Testproject SMOKE onbekende projectleider',
            'leadEmployeeId' => 999999,
        ])->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['leadEmployeeId']]);

        // And a room without a name is pointed at, not swallowed.
        $this->postJson('/api/v1/knx/projects', [
            'clientId' => $this->uvVastgoedId(),
            'code' => 'SMOKE-X3',
            'name' => 'Testproject SMOKE ruimte zonder naam',
            'rooms' => [['floor' => '1e verdieping']],
        ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['rooms.0.name']]);
    }

    public function test_the_write_routes_refuse_everyone_who_is_not_the_office(): void
    {
        // No token at all.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/knx/clients', ['name' => 'x'])->assertUnauthorized();
        $this->postJson('/api/v1/knx/projects', ['clientId' => 1, 'code' => 'x', 'name' => 'x'])->assertUnauthorized();

        // A field account (Veld) on the office endpoint, like the session tests
        // assert for the reads: the two apps do not accept each other's accounts.
        $this->actingAs(
            User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(),
            'sanctum',
        );
        $this->postJson('/api/v1/knx/clients', ['name' => 'x'])->assertUnauthorized();
        $this->postJson('/api/v1/knx/projects', ['clientId' => 1, 'code' => 'x', 'name' => 'x'])->assertUnauthorized();

        // Nothing was written by any of the four attempts above.
        $this->assertSame(0, KnxClient::query()->where('name', 'x')->count());
        $this->assertSame(0, KnxProject::query()->where('code', 'x')->count());
    }

    public function test_a_project_cannot_reference_another_organizations_client_or_lead(): void
    {
        // A second tenant, with a client and an employee of its own.
        $other = Organization::factory()->create(['slug' => 'other-company']);
        $foreignClient = KnxClient::factory()->create(['organization_id' => $other->id]);
        $foreignLead = KnxEmployee::factory()->forOrganization($other)->create();

        // Both references are refused. Without the organization scope on the exists
        // rules the project would be created and its response would load the foreign
        // row: a cross-tenant leak, not a validation nicety.
        $this->postJson('/api/v1/knx/projects', [
            'clientId' => $foreignClient->id,
            'code' => 'CROSS1',
            'name' => 'Cross tenant client',
        ])->assertStatus(422)->assertJsonValidationErrors('clientId');

        $this->postJson('/api/v1/knx/projects', [
            'clientId' => $this->uvVastgoedId(),
            'code' => 'CROSS2',
            'name' => 'Cross tenant lead',
            'leadEmployeeId' => $foreignLead->id,
        ])->assertStatus(422)->assertJsonValidationErrors('leadEmployeeId');

        // And neither attempt created anything.
        $this->assertSame(0, KnxProject::query()->whereIn('code', ['CROSS1', 'CROSS2'])->count());
    }
}
