<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\FoClientResource;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoClient;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FoClientFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_client_pages_render(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $withComplex = FoClient::factory()->create();
        Complex::factory()->create(['client_id' => $withComplex->id]);
        $withoutComplex = FoClient::factory()->create();

        $this->get('/fo-clients')->assertOk();
        $this->get("/fo-clients/{$withComplex->id}")->assertOk();
        $this->get("/fo-clients/{$withoutComplex->id}")->assertOk();
    }

    public function test_edit_route_no_longer_exists(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $client = FoClient::factory()->create();

        $this->get("/fo-clients/{$client->id}/edit")->assertNotFound();
        $this->assertFalse(FoClientResource::canCreate());
        $this->assertFalse(FoClientResource::canEdit($client));
        $this->assertFalse(FoClientResource::canDelete($client));
        $this->assertFalse(FoClientResource::canDeleteAny());
        $this->assertFalse(FoClientResource::canRestore($client));
        $this->assertFalse(FoClientResource::canRestoreAny());
    }
}
