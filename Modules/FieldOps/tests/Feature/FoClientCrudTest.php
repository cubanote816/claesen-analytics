<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoClient;
use Tests\TestCase;

class FoClientCrudTest extends TestCase
{
    use RefreshDatabase;

    private function token(): string
    {
        return UserFactory::new()->create()->createToken('test')->plainTextToken;
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
}
