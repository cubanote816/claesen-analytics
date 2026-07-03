<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['project_manager', 'super_admin', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function activeUser(): User
    {
        return UserFactory::new()->create([
            'password'        => bcrypt('Secret1234!'),
            'password_set_at' => now()->subDay(),
            'is_active'       => true,
        ]);
    }

    public function test_project_manager_has_no_panel_access(): void
    {
        $user = $this->activeUser();
        $user->assignRole('project_manager');

        $this->assertFalse($user->hasPanelAccess());
    }

    public function test_admin_and_super_admin_have_panel_access(): void
    {
        $admin = $this->activeUser();
        $admin->assignRole('admin');
        $this->assertTrue($admin->hasPanelAccess());

        $superAdmin = $this->activeUser();
        $superAdmin->assignRole('super_admin');
        $this->assertTrue($superAdmin->hasPanelAccess());
    }

    public function test_project_manager_is_redirected_away_from_the_panel(): void
    {
        $user = $this->activeUser();
        $user->assignRole('project_manager');

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('auth.no-access'));
    }

    public function test_project_manager_sees_the_welcome_page(): void
    {
        $user = $this->activeUser();
        $user->assignRole('project_manager');

        $this->actingAs($user)
            ->get(route('auth.no-access'))
            ->assertOk()
            ->assertSee('Nog geen toegang');
    }

    public function test_user_with_panel_access_is_redirected_away_from_the_welcome_page(): void
    {
        $user = $this->activeUser();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get(route('auth.no-access'))
            ->assertRedirect('/');
    }

    public function test_project_manager_can_still_log_out_from_the_welcome_page(): void
    {
        $user = $this->activeUser();
        $user->assignRole('project_manager');

        $this->actingAs($user)
            ->post(route('filament.admin.auth.logout'))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }
}
