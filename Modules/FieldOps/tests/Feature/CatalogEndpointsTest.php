<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Models\AccessType;
use Modules\FieldOps\Models\ElectricalBoardType;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;
use Modules\FieldOps\Models\SafetyType;
use Modules\FieldOps\Models\StructureType;
use Modules\Intelligence\Services\GeminiService;
use Tests\TestCase;

class CatalogEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
    }

    private function token(): string
    {
        $user = UserFactory::new()->create();

        return $user->createToken('test')->plainTextToken;
    }

    public function test_structure_types_returns_all_with_translations(): void
    {
        StructureType::factory()->count(2)->create();

        $response = $this->withToken($this->token())->getJson('/api/v1/fieldops/structure-types');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    public function test_access_types_returns_all(): void
    {
        AccessType::factory()->count(3)->create();

        $response = $this->withToken($this->token())->getJson('/api/v1/fieldops/access-types');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_safety_types_returns_all(): void
    {
        SafetyType::factory()->count(1)->create();

        $response = $this->withToken($this->token())->getJson('/api/v1/fieldops/safety-types');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_electrical_board_types_returns_all(): void
    {
        ElectricalBoardType::factory()->count(2)->create();

        $response = $this->withToken($this->token())->getJson('/api/v1/fieldops/electrical-board-types');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_luminaire_frame_types_returns_all_with_image_field(): void
    {
        LuminaireFrameType::factory()->create();

        $response = $this->withToken($this->token())->getJson('/api/v1/fieldops/luminaire-frame-types');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'image']]]);
    }

    public function test_luminaire_types_returns_all_with_subgroup_id(): void
    {
        LuminaireType::factory()->create();

        $response = $this->withToken($this->token())->getJson('/api/v1/fieldops/luminaire-types');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'image', 'luminaire_subgroup_id']]]);
    }

    public function test_luminaire_subgroups_returns_all(): void
    {
        LuminaireSubgroup::factory()->count(2)->create();

        $response = $this->withToken($this->token())->getJson('/api/v1/fieldops/luminaire-subgroups');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'group_name', 'brand']]]);
    }

    public function test_catalog_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/fieldops/structure-types')->assertUnauthorized();
        $this->getJson('/api/v1/fieldops/access-types')->assertUnauthorized();
        $this->getJson('/api/v1/fieldops/safety-types')->assertUnauthorized();
        $this->getJson('/api/v1/fieldops/electrical-board-types')->assertUnauthorized();
        $this->getJson('/api/v1/fieldops/luminaire-frame-types')->assertUnauthorized();
        $this->getJson('/api/v1/fieldops/luminaire-types')->assertUnauthorized();
        $this->getJson('/api/v1/fieldops/luminaire-subgroups')->assertUnauthorized();
    }
}
