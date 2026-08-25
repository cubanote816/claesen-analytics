<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\Intelligence\Filament\Pages\MirrorSyncStatusPage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-404 — unlike the sync trigger previously buried in BiConfigPage
 * (super_admin only), this page must be reachable by gerencia roles too.
 */
class MirrorSyncStatusPageAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_can_access(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));

        $this->assertTrue(MirrorSyncStatusPage::canAccess());
    }

    public function test_admin_can_access(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        $this->assertTrue(MirrorSyncStatusPage::canAccess());
    }

    public function test_financial_manager_can_access(): void
    {
        $this->actingAs($this->userWithRole('financial_manager'));

        $this->assertTrue(MirrorSyncStatusPage::canAccess());
    }

    public function test_hr_manager_cannot_access(): void
    {
        $this->actingAs($this->userWithRole('hr_manager'));

        $this->assertFalse(MirrorSyncStatusPage::canAccess());
    }

    public function test_viewer_cannot_access(): void
    {
        $this->actingAs($this->userWithRole('viewer'));

        $this->assertFalse(MirrorSyncStatusPage::canAccess());
    }

    public function test_project_manager_cannot_access(): void
    {
        $this->actingAs($this->userWithRole('project_manager'));

        $this->assertFalse(MirrorSyncStatusPage::canAccess());
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $this->assertFalse(MirrorSyncStatusPage::canAccess());
    }
}
