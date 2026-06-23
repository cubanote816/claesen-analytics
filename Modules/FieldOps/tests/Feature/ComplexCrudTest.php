<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoClient;
use Tests\TestCase;

class ComplexCrudTest extends TestCase
{
    use RefreshDatabase;

    private function user(): array
    {
        $user  = UserFactory::new()->create();
        $token = $user->createToken('test')->plainTextToken;

        return [$user, $token];
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_complex_and_returns_201(): void
    {
        [, $token] = $this->user();

        $response = $this->withToken($token)->postJson('/api/v1/fieldops/complexes', [
            'name'    => 'Sportpark Leuven',
            'city'    => 'Leuven',
            'street'  => 'Tiensesteenweg 145',
            'zipcode' => '3000',
            'lat'     => 50.8798,
            'lng'     => 4.7005,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Sportpark Leuven')
            ->assertJsonPath('data.city', 'Leuven');

        $this->assertDatabaseHas('fo_complexes', ['name' => 'Sportpark Leuven']);
    }

    public function test_store_injects_created_by_user_id(): void
    {
        [$user, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/complexes', [
            'name' => 'Test Complex',
        ]);

        $this->assertDatabaseHas('fo_complexes', [
            'name'               => 'Test Complex',
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/fieldops/complexes', ['name' => 'X'])
            ->assertStatus(401);
    }

    public function test_store_requires_name(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/complexes', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_store_rejects_nonexistent_client_id(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/complexes', [
            'name'      => 'Test',
            'client_id' => 99999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('client_id');
    }

    public function test_store_accepts_null_client_id(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->postJson('/api/v1/fieldops/complexes', [
            'name'      => 'Zonder klant',
            'client_id' => null,
        ])->assertStatus(201)
            ->assertJsonPath('data.client', null);
    }

    public function test_store_with_valid_client_id(): void
    {
        [, $token] = $this->user();
        $client = FoClient::factory()->create();

        $response = $this->withToken($token)->postJson('/api/v1/fieldops/complexes', [
            'name'      => 'Met klant',
            'client_id' => $client->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.client.id', $client->id);
    }

    public function test_store_response_shape_matches_show(): void
    {
        [$user, $token] = $this->user();
        $client = FoClient::factory()->create();

        $storeResponse = $this->withToken($token)->postJson('/api/v1/fieldops/complexes', [
            'name'      => 'Shape Test',
            'client_id' => $client->id,
        ]);

        $storeResponse->assertStatus(201);
        $id = $storeResponse->json('data.id');

        $showResponse = $this->withToken($token)->getJson("/api/v1/fieldops/complexes/{$id}");
        $showResponse->assertStatus(200);

        $storeKeys = array_keys($storeResponse->json('data'));
        $showKeys  = array_keys($showResponse->json('data'));

        foreach ($storeKeys as $key) {
            $this->assertContains($key, $showKeys, "Key '{$key}' present in store but missing in show");
        }
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_patches_only_sent_fields(): void
    {
        [$user, $token] = $this->user();
        $complex = Complex::factory()->create(['city' => 'Gent', 'name' => 'Origineel']);

        $this->withToken($token)->patchJson("/api/v1/fieldops/complexes/{$complex->id}", [
            'city' => 'Brussel',
        ])->assertStatus(200)
            ->assertJsonPath('data.city', 'Brussel')
            ->assertJsonPath('data.name', 'Origineel');
    }

    public function test_update_via_put_also_works(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create(['name' => 'Oud']);

        $this->withToken($token)->putJson("/api/v1/fieldops/complexes/{$complex->id}", [
            'name' => 'Nieuw',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Nieuw');
    }

    public function test_update_cannot_change_created_by_user_id(): void
    {
        [$user, $token] = $this->user();
        $other = UserFactory::new()->create();
        $complex = Complex::factory()->create(['created_by_user_id' => $user->id]);

        $this->withToken($token)->patchJson("/api/v1/fieldops/complexes/{$complex->id}", [
            'created_by_user_id' => $other->id,
        ])->assertStatus(200);

        $this->assertDatabaseHas('fo_complexes', [
            'id'                 => $complex->id,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_update_returns_404_for_missing_complex(): void
    {
        [, $token] = $this->user();

        $this->withToken($token)->patchJson('/api/v1/fieldops/complexes/99999', [
            'name' => 'Ghost',
        ])->assertStatus(404);
    }

    public function test_update_accepts_null_client_id(): void
    {
        [, $token] = $this->user();
        $client  = FoClient::factory()->create();
        $complex = Complex::factory()->create(['client_id' => $client->id]);

        $this->withToken($token)->patchJson("/api/v1/fieldops/complexes/{$complex->id}", [
            'client_id' => null,
        ])->assertStatus(200)
            ->assertJsonPath('data.client', null);
    }

    public function test_update_requires_authentication(): void
    {
        $complex = Complex::factory()->create();

        $this->patchJson("/api/v1/fieldops/complexes/{$complex->id}", ['name' => 'X'])
            ->assertStatus(401);
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_returns_204(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/complexes/{$complex->id}")
            ->assertStatus(204);
    }

    public function test_destroy_soft_deletes_record(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/complexes/{$complex->id}");

        $this->assertSoftDeleted('fo_complexes', ['id' => $complex->id]);
    }

    public function test_destroy_then_get_returns_404(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/complexes/{$complex->id}")
            ->assertStatus(204);

        $this->withToken($token)->getJson("/api/v1/fieldops/complexes/{$complex->id}")
            ->assertStatus(404);
    }

    public function test_destroy_already_deleted_returns_404(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create();
        $complex->delete();

        $this->withToken($token)->deleteJson("/api/v1/fieldops/complexes/{$complex->id}")
            ->assertStatus(404);
    }

    public function test_destroy_requires_authentication(): void
    {
        $complex = Complex::factory()->create();

        $this->deleteJson("/api/v1/fieldops/complexes/{$complex->id}")
            ->assertStatus(401);
    }

    public function test_destroyed_complex_not_in_index(): void
    {
        [, $token] = $this->user();
        $complex = Complex::factory()->create(['name' => 'Te verwijderen']);

        $this->withToken($token)->deleteJson("/api/v1/fieldops/complexes/{$complex->id}");

        $response = $this->withToken($token)->getJson('/api/v1/fieldops/complexes');
        $names    = collect($response->json('data'))->pluck('name')->all();

        $this->assertNotContains('Te verwijderen', $names);
    }

    // ── index filter: client_id ───────────────────────────────────────────────

    public function test_index_filters_by_client_id(): void
    {
        [, $token] = $this->user();
        $clientA = FoClient::factory()->create();
        $clientB = FoClient::factory()->create();

        Complex::factory()->create(['client_id' => $clientA->id, 'name' => 'Complex A']);
        Complex::factory()->create(['client_id' => $clientB->id, 'name' => 'Complex B']);

        $response = $this->withToken($token)
            ->getJson("/api/v1/fieldops/complexes?client_id={$clientA->id}")
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Complex A', $names);
        $this->assertNotContains('Complex B', $names);
    }

    public function test_index_without_client_id_returns_all(): void
    {
        [, $token] = $this->user();
        $clientA = FoClient::factory()->create();
        $clientB = FoClient::factory()->create();

        Complex::factory()->create(['client_id' => $clientA->id, 'name' => 'Complex A']);
        Complex::factory()->create(['client_id' => $clientB->id, 'name' => 'Complex B']);

        $response = $this->withToken($token)
            ->getJson('/api/v1/fieldops/complexes')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Complex A', $names);
        $this->assertContains('Complex B', $names);
    }
}
