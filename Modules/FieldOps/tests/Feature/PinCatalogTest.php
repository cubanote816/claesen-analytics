<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Support\ElectricalBoardPinCatalog;
use Modules\FieldOps\Support\StructurePinCatalog;
use Modules\FieldOps\Support\TerrainPinCatalog;
use Tests\TestCase;

class PinCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_fieldops_user_can_read_the_pin_catalog_not_only_client_portal_users(): void
    {
        // A plain employee/backoffice-less user — not a "client" — must not be blocked the
        // way ClientPortalInfrastructureController blocks non-client users (CLA-343: the
        // field app authenticates as an employee, never as a client).
        $user = UserFactory::new()->create();
        $token = $user->createToken('field-app')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/fieldops/pin-catalog')
            ->assertOk()
            ->assertJsonCount(count(TerrainPinCatalog::definitions()), 'data.terrain')
            ->assertJsonCount(count(StructurePinCatalog::definitions()), 'data.structure')
            ->assertJsonPath('data.terrain.0.code', TerrainPinCatalog::definitions()[0]['code'])
            ->assertJsonPath('data.structure_fallback_svg', StructurePinCatalog::fallbackSvg())
            ->assertJsonPath('data.electrical_board_svg', ElectricalBoardPinCatalog::svg());
    }

    public function test_terrain_entries_keep_the_unsubstituted_fill_placeholder_for_client_side_tinting(): void
    {
        $user = UserFactory::new()->create();
        $token = $user->createToken('field-app')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/fieldops/pin-catalog')->assertOk();

        foreach ($response->json('data.terrain') as $entry) {
            $this->assertStringContainsString('${fill}', $entry['svg']);
        }
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/fieldops/pin-catalog')->assertUnauthorized();
    }
}
