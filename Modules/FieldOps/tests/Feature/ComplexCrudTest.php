<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoClient;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ComplexCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
    }

    private function user(): array
    {
        $user = UserFactory::new()->create();
        // CLA-364: broad FieldOps access is no longer the default for any
        // authenticated user — this helper simulates internal staff, so it
        // needs the permission explicitly, same as an admin/super_admin would
        // get from RolesAndPermissionsSeeder in a real environment.
        $user->givePermissionTo(Permission::findOrCreate('fieldops.view-all-clients', 'web'));
        $token = $user->createToken('test')->plainTextToken;

        return [$user, $token];
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
            'id' => $complex->id,
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

    public function test_update_ignores_client_id_the_client_complex_link_is_immutable_here(): void
    {
        [, $token] = $this->user();
        $client = FoClient::factory()->create();
        $complex = Complex::factory()->create(['client_id' => $client->id]);

        $this->withToken($token)->patchJson("/api/v1/fieldops/complexes/{$complex->id}", [
            'client_id' => null,
        ])->assertStatus(200)
            ->assertJsonPath('data.client.id', $client->id);

        $this->assertDatabaseHas('fo_complexes', ['id' => $complex->id, 'client_id' => $client->id]);
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
        $names = collect($response->json('data'))->pluck('name')->all();

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

    // ── index filter: search ─────────────────────────────────────────────────

    public function test_index_search_matches_name(): void
    {
        [, $token] = $this->user();
        Complex::factory()->create(['name' => 'Sportpark Balen']);
        Complex::factory()->create(['name' => 'Sportpark Leuven']);

        $response = $this->withToken($token)
            ->getJson('/api/v1/fieldops/complexes?search=balen')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Sportpark Balen', $names);
        $this->assertNotContains('Sportpark Leuven', $names);
    }

    public function test_index_search_is_case_insensitive(): void
    {
        [, $token] = $this->user();
        Complex::factory()->create(['name' => 'Sportpark Balen']);

        $response = $this->withToken($token)
            ->getJson('/api/v1/fieldops/complexes?search=BALEN')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Sportpark Balen', $names);
    }

    public function test_index_search_matches_city(): void
    {
        [, $token] = $this->user();
        Complex::factory()->create(['name' => 'Terrein 1', 'city' => 'Balen']);
        Complex::factory()->create(['name' => 'Terrein 2', 'city' => 'Leuven']);

        $response = $this->withToken($token)
            ->getJson('/api/v1/fieldops/complexes?search=balen')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Terrein 1', $names);
        $this->assertNotContains('Terrein 2', $names);
    }

    public function test_index_search_matches_street(): void
    {
        [, $token] = $this->user();
        Complex::factory()->create(['name' => 'Terrein 1', 'street' => 'Balenstraat 12']);
        Complex::factory()->create(['name' => 'Terrein 2', 'street' => 'Leuvensesteenweg 1']);

        $response = $this->withToken($token)
            ->getJson('/api/v1/fieldops/complexes?search=balenstraat')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Terrein 1', $names);
        $this->assertNotContains('Terrein 2', $names);
    }

    public function test_index_search_matches_client_name(): void
    {
        [, $token] = $this->user();
        $clientBalen = FoClient::factory()->create(['name' => 'Gemeente Balen']);
        $clientOther = FoClient::factory()->create(['name' => 'Gemeente Leuven']);

        Complex::factory()->create(['client_id' => $clientBalen->id, 'name' => 'Terrein 1']);
        Complex::factory()->create(['client_id' => $clientOther->id, 'name' => 'Terrein 2']);

        $response = $this->withToken($token)
            ->getJson('/api/v1/fieldops/complexes?search=balen')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Terrein 1', $names);
        $this->assertNotContains('Terrein 2', $names);
    }

    public function test_index_search_combines_with_client_id_filter(): void
    {
        [, $token] = $this->user();
        $clientA = FoClient::factory()->create();
        $clientB = FoClient::factory()->create();

        Complex::factory()->create(['client_id' => $clientA->id, 'name' => 'Balen A']);
        Complex::factory()->create(['client_id' => $clientB->id, 'name' => 'Balen B']);

        $response = $this->withToken($token)
            ->getJson("/api/v1/fieldops/complexes?search=balen&client_id={$clientA->id}")
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Balen A', $names);
        $this->assertNotContains('Balen B', $names);
    }

    public function test_index_search_no_match_returns_empty(): void
    {
        [, $token] = $this->user();
        Complex::factory()->create(['name' => 'Sportpark Leuven']);

        $response = $this->withToken($token)
            ->getJson('/api/v1/fieldops/complexes?search=zzz-nonexistent')
            ->assertOk();

        $this->assertSame([], $response->json('data'));
    }

    public function test_index_search_requires_authentication(): void
    {
        $this->getJson('/api/v1/fieldops/complexes?search=balen')
            ->assertStatus(401);
    }
}
