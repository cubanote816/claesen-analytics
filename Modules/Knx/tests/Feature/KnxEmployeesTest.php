<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxEmployee;
use Tests\TestCase;

/**
 * `GET /employees` (KNX-3).
 *
 * Exists because `POST /projects` accepts `leadEmployeeId` but nothing listed
 * employees: `GET /technicians` is `field()->active()` only, so the office could
 * send a lead id but never build the selector with real data.
 */
final class KnxEmployeesTest extends TestCase
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

    public function test_it_lists_the_active_office_employees_in_the_contract_shape(): void
    {
        $employees = $this->getJson('/api/v1/knx/employees')->assertOk()->json();

        // Office by default: that is the selector this route serves.
        $this->assertSame(['id', 'name', 'shortName'], array_keys($employees[0]));
        $this->assertSame(
            ['L. Smet', 'P. Aerts'],
            array_column($employees, 'shortName'),
        );

        // The id the create form sends back is the string the contract uses.
        $this->assertSame((string) KnxEmployee::query()->where('name', 'Lien Smet')->sole()->id, $employees[0]['id']);

        // Field staff are not office people, and the technician list is where they live.
        $this->assertNotContains('J. Van Dyck', array_column($employees, 'shortName'));
    }

    public function test_the_role_filter_can_list_field_employees_with_the_same_shape(): void
    {
        $technicians = $this->getJson('/api/v1/knx/employees?role=field')->assertOk()->json();

        $this->assertSame(['id', 'name', 'shortName'], array_keys($technicians[0]));
        $this->assertContains('J. Van Dyck', array_column($technicians, 'shortName'));
        $this->assertNotContains('L. Smet', array_column($technicians, 'shortName'));
    }

    public function test_an_inactive_employee_is_not_offered(): void
    {
        KnxEmployee::query()->where('name', 'Pieter Aerts')->update(['active' => false]);

        $employees = $this->getJson('/api/v1/knx/employees')->assertOk()->json();

        $this->assertSame(['L. Smet'], array_column($employees, 'shortName'));
    }

    public function test_an_unknown_role_is_a_field_error(): void
    {
        $this->getJson('/api/v1/knx/employees?role=onzin')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['role']]);
    }

    public function test_the_endpoint_belongs_to_the_office_app(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/knx/employees')->assertUnauthorized();

        $this->actingAs(
            User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(),
            'sanctum',
        );
        $this->getJson('/api/v1/knx/employees')->assertUnauthorized();
    }
}
