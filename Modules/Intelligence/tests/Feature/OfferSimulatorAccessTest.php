<?php

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\Intelligence\Filament\Pages\OfferSimulator;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-223 — Offer Simulator is too rough for general access; restricted
 * to super_admin until the implementation matures.
 */
class OfferSimulatorAccessTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    private function regularUser(string $role = 'admin'): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_can_access_offer_simulator(): void
    {
        $this->actingAs($this->superAdmin());

        $this->assertTrue(OfferSimulator::canAccess());
    }

    public function test_admin_cannot_access_offer_simulator(): void
    {
        $this->actingAs($this->regularUser('admin'));

        $this->assertFalse(OfferSimulator::canAccess());
    }

    public function test_project_manager_cannot_access_offer_simulator(): void
    {
        $this->actingAs($this->regularUser('project_manager'));

        $this->assertFalse(OfferSimulator::canAccess());
    }

    public function test_unauthenticated_cannot_access_offer_simulator(): void
    {
        $this->assertFalse(OfferSimulator::canAccess());
    }

    public function test_super_admin_cannot_access_offer_simulator_in_production(): void
    {
        $this->actingAs($this->superAdmin());
        app()->instance('env', 'production');

        $this->assertFalse(OfferSimulator::canAccess());
    }

    public function test_super_admin_can_access_offer_simulator_outside_production(): void
    {
        $this->actingAs($this->superAdmin());
        app()->instance('env', 'staging');

        $this->assertTrue(OfferSimulator::canAccess());
    }

    /**
     * CLA-224 — hidden from the sidebar in every environment; still
     * directly reachable by URL under the CLA-223 canAccess() gate.
     */
    public function test_navigation_item_is_never_registered(): void
    {
        $this->assertFalse(OfferSimulator::shouldRegisterNavigation());

        app()->instance('env', 'staging');
        $this->assertFalse(OfferSimulator::shouldRegisterNavigation());
    }
}
