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

    private function user(): array
    {
        $user  = UserFactory::new()->create();
        $token = $user->createToken('test')->plainTextToken;

        return [$user, $token];
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_client_and_returns_201(): void
    {
        [, $token] = $this->user();

        $response = $this->withToken($token)->postJson('/api/v1/fieldops/clients', [
            'name'  => 'Sportclub Merksem',
            'city'  => 'Antwerpen',
            'email' => 'info@sportclub.be',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Sportclub Merksem')
            ->assertJsonPath('data.city', 'Antwerpen');

        $this->assertDatabaseHas('fo_clients', ['name' => 'Sportclub Merksem']);
    }

    public function test_store_requires_name(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/clients', [
            'city' => 'Antwerpen',
        ])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_store_rejects_invalid_email(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/clients', [
            'name'  => 'Test Client',
            'email' => 'not-an-email',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_store_rejects_invalid_language(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/clients', [
            'name'     => 'Test Client',
            'language' => 'es',
        ])->assertStatus(422)->assertJsonValidationErrors('language');
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/fieldops/clients', ['name' => 'Test Client'])
            ->assertStatus(401);
    }

    // ── index / show ──────────────────────────────────────────────────────────

    public function test_index_returns_all_clients_with_complexes_count(): void
    {
        [, $token] = $this->user();
        $client = FoClient::factory()->create();
        Complex::factory()->count(2)->create(['client_id' => $client->id]);

        $response = $this->withToken($token)->getJson('/api/v1/fieldops/clients')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.complexes_count', 2);
    }

    public function test_show_returns_client(): void
    {
        [, $token] = $this->user();
        $client = FoClient::factory()->create();

        $this->withToken($token)->getJson("/api/v1/fieldops/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $client->id);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_patches_only_sent_fields(): void
    {
        [, $token] = $this->user();
        $client = FoClient::factory()->create(['city' => 'Gent']);

        $this->withToken($token)->patchJson("/api/v1/fieldops/clients/{$client->id}", [
            'city' => 'Brugge',
        ])->assertStatus(200)->assertJsonPath('data.city', 'Brugge');

        $this->assertDatabaseHas('fo_clients', ['id' => $client->id, 'city' => 'Brugge']);
    }

    public function test_update_returns_404_for_missing_client(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->patchJson('/api/v1/fieldops/clients/99999', ['city' => 'Gent'])
            ->assertStatus(404);
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_client(): void
    {
        [, $token] = $this->user();
        $client = FoClient::factory()->create();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/clients/{$client->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('fo_clients', ['id' => $client->id]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $client = FoClient::factory()->create();

        $this->deleteJson("/api/v1/fieldops/clients/{$client->id}")->assertStatus(401);
    }
}
