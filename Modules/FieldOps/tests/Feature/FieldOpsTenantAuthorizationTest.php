<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FieldOpsTenantAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    }

    public function test_client_user_only_lists_linked_client_topology(): void
    {
        $a = $this->topology('Client A');
        $b = $this->topology('Client B');
        [$user, $token] = $this->clientUser($a['client']);

        $this->assertTrue($user->fieldOpsClients->contains($a['client']));

        $this->withToken($token)->getJson('/api/v1/fieldops/clients')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['client']->id);
        $this->withToken($token)->getJson('/api/v1/fieldops/complexes')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['complex']->id);
        $this->withToken($token)->getJson('/api/v1/fieldops/terrains')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['terrain']->id);
        $this->withToken($token)->getJson('/api/v1/fieldops/structures')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['structure']->id);
        $this->withToken($token)->getJson('/api/v1/fieldops/luminaire-frames')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['frame']->id);
        $this->withToken($token)->getJson('/api/v1/fieldops/electrical-boards')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['board']->id);
        $this->withToken($token)->getJson('/api/v1/fieldops/terrains/count')
            ->assertOk()->assertJsonPath('data.total', 1);

        $this->assertNotSame($a['client']->id, $b['client']->id);
    }

    public function test_client_user_cannot_access_another_clients_objects_or_history(): void
    {
        $a = $this->topology('Client A');
        $b = $this->topology('Client B');
        [, $token] = $this->clientUser($a['client']);
        $record = FoMaintenanceRecord::factory()->forMaintainable($b['luminaire'])->create([
            'client_id' => $b['client']->id,
        ]);
        $media = Media::query()->create([
            'model_type' => Complex::class,
            'model_id' => $b['complex']->id,
            'collection_name' => 'photos',
            'name' => 'private-site-photo',
            'file_name' => 'private-site-photo.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'local',
            'size' => 1,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        foreach ([
            "/api/v1/fieldops/clients/{$b['client']->id}",
            "/api/v1/fieldops/complexes/{$b['complex']->id}",
            "/api/v1/fieldops/terrains/{$b['terrain']->id}",
            "/api/v1/fieldops/structures/{$b['structure']->id}",
            "/api/v1/fieldops/luminaire-frames/{$b['frame']->id}",
            "/api/v1/fieldops/luminaires/{$b['luminaire']->id}",
            "/api/v1/fieldops/electrical-boards/{$b['board']->id}",
            "/api/v1/fieldops/maintenance-records/{$record->id}",
            "/api/v1/fieldops/media/{$media->id}",
        ] as $url) {
            $this->withToken($token)->getJson($url)->assertForbidden();
        }
    }

    public function test_client_role_is_read_only_and_cannot_access_internal_work_orders(): void
    {
        $a = $this->topology('Client A');
        [, $token] = $this->clientUser($a['client']);

        $this->withToken($token)
            ->patchJson("/api/v1/fieldops/complexes/{$a['complex']->id}", ['name' => 'Changed'])
            ->assertForbidden();
        $this->withToken($token)
            ->getJson('/api/v1/fieldops/maintenance-work-orders/assigned')
            ->assertForbidden();

        $this->assertDatabaseHas('fo_complexes', ['id' => $a['complex']->id, 'name' => 'Client A site']);
    }

    public function test_inactive_or_non_viewable_membership_grants_no_access(): void
    {
        $a = $this->topology('Client A');
        [$user, $token] = $this->clientUser($a['client']);
        $user->fieldOpsClients()->updateExistingPivot($a['client']->id, ['can_view' => false]);

        $this->withToken($token)->getJson('/api/v1/fieldops/clients')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($token)->getJson("/api/v1/fieldops/complexes/{$a['complex']->id}")->assertForbidden();
    }

    public function test_ambiguous_cross_client_equipment_is_hidden_from_both_clients(): void
    {
        $a = $this->topology('Client A');
        $b = $this->topology('Client B');
        $a['board']->complexes()->attach($b['complex']);
        [, $token] = $this->clientUser($a['client']);

        $this->withToken($token)->getJson('/api/v1/fieldops/electrical-boards')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($token)->getJson("/api/v1/fieldops/electrical-boards/{$a['board']->id}")
            ->assertForbidden();
    }

    private function clientUser(FoClient $client): array
    {
        $user = UserFactory::new()->create();
        $user->assignRole('client');
        $user->fieldOpsClients()->attach($client->id, [
            'is_active' => true,
            'can_view' => true,
            'can_report' => true,
        ]);

        return [$user, $user->createToken('client-portal')->plainTextToken];
    }

    private function topology(string $name): array
    {
        $client = FoClient::factory()->create(['name' => $name]);
        $complex = Complex::factory()->create(['client_id' => $client->id, 'name' => "{$name} site"]);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain);
        $frame = LuminaireFrame::factory()->create();
        $frame->structures()->attach($structure);
        $luminaire = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id]);
        $board = ElectricalBoard::factory()->create();
        $board->complexes()->attach($complex);

        return compact('client', 'complex', 'terrain', 'structure', 'frame', 'luminaire', 'board');
    }
}
