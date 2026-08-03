<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\StructureType;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Models\TerrainType;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientPortalInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    }

    public function test_client_portal_returns_only_the_members_authorised_topology_and_reduced_payload(): void
    {
        $allowed = $this->topology('Allowed client');
        $hidden = $this->topology('Hidden client');
        [, $token] = $this->clientUser($allowed['client']);

        $this->withToken($token)->getJson('/api/v1/fieldops/client-portal/infrastructure')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $allowed['complex']->id)
            ->assertJsonPath('data.0.terrains.0.id', $allowed['terrain']->id)
            ->assertJsonPath('data.0.terrains.0.pin.code', 'soccer')
            ->assertJsonPath('data.0.terrains.0.pin.color', '#4c8c4a')
            ->assertJsonPath('data.0.terrains.0.structures.0.pin.code', 'conical')
            ->assertJsonPath('data.0.electrical_boards.0.pin.type', 'electrical-board')
            ->assertJsonPath('data.0.electrical_boards.0.pin.svg', \Modules\FieldOps\Support\ElectricalBoardPinCatalog::svg())
            ->assertJsonPath('data.0.terrains.0.structures.0.frames.0.positions.0.id', $allowed['luminaire']->luminaire_position_id)
            ->assertJsonPath('data.0.terrains.0.structures.0.frames.0.positions.0.luminaire_id', $allowed['luminaire']->id)
            ->assertJsonMissing(['id' => $hidden['complex']->id])
            ->assertJsonMissingPath('data.0.client_id')
            ->assertJsonMissingPath('data.0.created_by')
            ->assertJsonMissingPath('data.0.terrains.0.structures.0.cafca_material_id')
            ->assertJsonMissingPath('data.0.terrains.0.structures.0.access_active');
    }

    public function test_client_portal_fails_closed_for_inactive_membership_and_internal_users(): void
    {
        $topology = $this->topology('Client');
        [$client, $token] = $this->clientUser($topology['client']);
        $client->fieldOpsClients()->updateExistingPivot($topology['client']->id, ['can_view' => false]);

        $this->withToken($token)->getJson('/api/v1/fieldops/client-portal/infrastructure')
            ->assertOk()->assertJsonCount(0, 'data');

    }

    public function test_client_portal_rejects_an_authenticated_internal_user(): void
    {
        $internal = UserFactory::new()->create();
        $internal->syncRoles([]);

        $this->withToken($internal->createToken('internal')->plainTextToken)
            ->getJson('/api/v1/fieldops/client-portal/infrastructure')->assertForbidden();
    }

    private function clientUser(FoClient $client): array
    {
        $user = UserFactory::new()->create();
        $user->assignRole('client');
        $user->fieldOpsClients()->attach($client->id, ['is_active' => true, 'can_view' => true, 'can_report' => true]);

        return [$user, $user->createToken('client-portal')->plainTextToken];
    }

    private function topology(string $name): array
    {
        $client = FoClient::factory()->create(['name' => $name]);
        $complex = Complex::factory()->create(['client_id' => $client->id, 'name' => "{$name} complex"]);
        $terrainType = TerrainType::factory()->create(['code' => 'soccer', 'pin_color' => '#4c8c4a']);
        $structureType = StructureType::factory()->create(['code' => 'conical', 'pin_color' => '#f5a524']);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id, 'terrain_type_id' => $terrainType->id, 'name' => ['en' => "{$name} terrain", 'nl' => "{$name} terrein"]]);
        $structure = Structure::factory()->create(['structure_type_id' => $structureType->id]);
        $structure->terrains()->attach($terrain);
        $frame = LuminaireFrame::factory()->create();
        $frame->structures()->attach($structure);
        $luminaire = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id, 'frame_position' => 4]);
        $board = ElectricalBoard::factory()->create();
        $board->complexes()->attach($complex);

        return compact('client', 'complex', 'terrain', 'structure', 'frame', 'luminaire', 'board');
    }
}
