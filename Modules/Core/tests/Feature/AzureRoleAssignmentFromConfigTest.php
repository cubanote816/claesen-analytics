<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\Core\Services\Auth\AzureRoleService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-532: AzureRoleService maps Azure group GUIDs to local roles strictly via
 * config('core.azure_role_mapping'). Empty/unset group entries are ignored and
 * a user with no mapped group falls back to 'viewer' — the previous env()
 * default array collapsed to an empty-string key and sent everyone to 'viewer'
 * unconditionally.
 *
 * DB-backed (RefreshDatabase). Authored here; executed in CI (no local MySQL).
 */
class AzureRoleAssignmentFromConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'admin', 'financial_manager', 'project_manager', 'viewer'] as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_a_mapped_group_assigns_the_configured_role(): void
    {
        config(['core.azure_role_mapping' => ['guid-admin' => 'admin']]);
        $user = $this->user();

        app(AzureRoleService::class)->syncRolesFromAzure($user, ['guid-admin', '']);

        $this->assertTrue($user->fresh()->hasRole('admin'));
        $this->assertFalse($user->fresh()->hasRole('viewer'));
    }

    public function test_no_mapped_group_falls_back_to_viewer(): void
    {
        config(['core.azure_role_mapping' => ['guid-admin' => 'admin']]);
        $user = $this->user();

        app(AzureRoleService::class)->syncRolesFromAzure($user, ['some-unmapped-guid']);

        $this->assertTrue($user->fresh()->hasRole('viewer'));
    }

    public function test_empty_mapping_falls_back_to_viewer_without_error(): void
    {
        config(['core.azure_role_mapping' => []]);
        $user = $this->user();

        app(AzureRoleService::class)->syncRolesFromAzure($user, ['whatever']);

        $this->assertTrue($user->fresh()->hasRole('viewer'));
    }

    public function test_existing_roles_are_left_untouched(): void
    {
        config(['core.azure_role_mapping' => ['guid-admin' => 'admin']]);
        $user = $this->user();
        $user->assignRole('super_admin');

        app(AzureRoleService::class)->syncRolesFromAzure($user, ['guid-admin']);

        $this->assertTrue($user->fresh()->hasRole('super_admin'));
        $this->assertFalse($user->fresh()->hasRole('admin'));
    }
}
