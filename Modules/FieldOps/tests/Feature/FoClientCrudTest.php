<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoClient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FoClientCrudTest extends TestCase
{
    use RefreshDatabase;

    private function token(): string
    {
        $user = UserFactory::new()->create();
        // CLA-364: broad FieldOps access needs the permission explicitly now.
        $user->givePermissionTo(Permission::findOrCreate('fieldops.view-all-clients', 'web'));

        return $user->createToken('test')->plainTextToken;
    }

    public function test_index_and_show_remain_available_to_authenticated_internal_users(): void
    {
        $client = FoClient::factory()->create();
        Complex::factory()->count(2)->create(['client_id' => $client->id]);

        $this->withToken($this->token())
            ->getJson('/api/v1/fieldops/clients')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.complexes_count', 2);

        $this->withToken($this->token())
            ->getJson("/api/v1/fieldops/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $client->id);
    }

    public function test_client_catalog_requires_authentication(): void
    {
        $this->getJson('/api/v1/fieldops/clients')->assertUnauthorized();
    }

    public function test_cafca_clients_cannot_be_created_updated_or_deleted_through_api(): void
    {
        $client = FoClient::factory()->create(['city' => 'Gent']);
        $token = $this->token();

        $this->withToken($token)->postJson('/api/v1/fieldops/clients', ['name' => 'Manual'])->assertMethodNotAllowed();
        $this->withToken($token)->patchJson("/api/v1/fieldops/clients/{$client->id}", ['city' => 'Brugge'])->assertMethodNotAllowed();
        $this->withToken($token)->deleteJson("/api/v1/fieldops/clients/{$client->id}")->assertMethodNotAllowed();

        $this->assertDatabaseHas('fo_clients', ['id' => $client->id, 'city' => 'Gent', 'deleted_at' => null]);
    }

    public function test_cafca_complexes_cannot_be_created_through_api(): void
    {
        $this->withToken($this->token())
            ->postJson('/api/v1/fieldops/complexes', ['name' => 'Manual site'])
            ->assertMethodNotAllowed();

        $this->assertDatabaseMissing('fo_complexes', ['name' => 'Manual site']);
    }

    // -------------------------------------------------------------------
    // CLA-556: FoClientResource.can_manage_contacts — the ACTOR's own
    // capability, discoverable without first having it (unlike the
    // GET/PATCH .../contacts endpoints themselves, which 403 without it).
    // -------------------------------------------------------------------
    public function test_can_manage_contacts_is_true_for_a_client_manager_and_false_for_a_plain_viewer(): void
    {
        foreach (['client', 'admin', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $client = FoClient::factory()->create();

        $manager = UserFactory::new()->create();
        $manager->assignRole('client');
        $manager->fieldOpsClients()->attach($client->id, [
            'is_active' => true, 'can_view' => true, 'can_report' => true, 'can_manage_contacts' => true,
        ]);

        $viewer = UserFactory::new()->create();
        $viewer->assignRole('client');
        $viewer->fieldOpsClients()->attach($client->id, [
            'is_active' => true, 'can_view' => true, 'can_report' => true, 'can_manage_contacts' => false,
        ]);

        $admin = UserFactory::new()->create();
        $admin->assignRole('admin');
        // Needed to pass EnforceFieldOpsTenantAccess's own 'view' gate on this
        // route (canView() → hasBroadAccess()) — unrelated to the
        // can_manage_contacts assertion below, which is admin-role-based.
        $admin->givePermissionTo(Permission::findOrCreate('fieldops.view-all-clients', 'web'));

        $this->withToken($manager->createToken('t')->plainTextToken)
            ->getJson("/api/v1/fieldops/clients/{$client->id}")
            ->assertOk()->assertJsonPath('data.can_manage_contacts', true);

        Auth::forgetGuards();
        $this->withToken($viewer->createToken('t')->plainTextToken)
            ->getJson("/api/v1/fieldops/clients/{$client->id}")
            ->assertOk()->assertJsonPath('data.can_manage_contacts', false);

        Auth::forgetGuards();
        $this->withToken($admin->createToken('t')->plainTextToken)
            ->getJson("/api/v1/fieldops/clients/{$client->id}")
            ->assertOk()->assertJsonPath('data.can_manage_contacts', true);
    }
}
