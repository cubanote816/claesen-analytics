<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Database\Factories\ComplexFactory;
use Modules\FieldOps\Database\Factories\TerrainTypeFactory;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\StructureType;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Models\TerrainType;
use Modules\Intelligence\Services\GeminiService;
use Tests\TestCase;

class AiTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function mockGemini(array $translations): void
    {
        $this->mock(GeminiService::class, function ($mock) use ($translations): void {
            $mock->shouldReceive('translateAndDetect')
                ->andReturn(['detected_locale' => 'nl', 'translations' => $translations]);
        });
    }

    // ── Terrain.name ─────────────────────────────────────────────────────────

    public function test_terrain_name_gets_ai_translations_when_missing(): void
    {
        $this->mockGemini(['en' => 'Sports field', 'fr' => 'Terrain de sport', 'de' => 'Sportplatz']);

        $terrain = Terrain::factory()->create([
            'name' => ['nl' => 'Sportveld'],
        ]);

        $terrain->refresh();

        $this->assertSame('Sports field', $terrain->getTranslation('name', 'en'));
        $this->assertSame('Terrain de sport', $terrain->getTranslation('name', 'fr'));
        $this->assertSame('Sportplatz', $terrain->getTranslation('name', 'de'));
        $this->assertSame('Sportveld', $terrain->getTranslation('name', 'nl'));
    }

    public function test_terrain_skips_gemini_on_update_when_name_not_dirty(): void
    {
        // Create with all locales — Gemini fills any missing ones
        $this->mockGemini(['en' => 'Field', 'fr' => 'Champ', 'de' => 'Feld']);
        $terrain = Terrain::create([
            'complex_id'         => ComplexFactory::new()->create()->id,
            'terrain_type_id'    => TerrainTypeFactory::new()->create()->id,
            'created_by_user_id' => null,
            'name'               => ['nl' => 'Veld'],
            'lat'                => 50.8,
            'lng'                => 4.3,
        ]);

        // Now update an unrelated field — Gemini must NOT be called (name is not dirty)
        $this->mock(GeminiService::class, fn ($m) => $m->shouldNotReceive('translateAndDetect'));
        $terrain->update(['lat' => 51.0]);
        $terrain->refresh();

        $this->assertSame('Field', $terrain->getTranslation('name', 'en'));
        $this->assertSame('Champ', $terrain->getTranslation('name', 'fr'));
    }

    // ── TerrainType.type ─────────────────────────────────────────────────────

    public function test_terrain_type_gets_ai_translations_when_missing(): void
    {
        $this->mockGemini(['en' => 'Parking lot', 'fr' => 'Parking', 'de' => 'Parkplatz']);

        $type = TerrainType::create(['type' => ['nl' => 'Parkeerplaats']]);
        $type->refresh();

        $this->assertSame('Parking lot', $type->getTranslation('type', 'en'));
        $this->assertSame('Parking', $type->getTranslation('type', 'fr'));
        $this->assertSame('Parkplatz', $type->getTranslation('type', 'de'));
    }

    // ── StructureType.name ───────────────────────────────────────────────────

    public function test_structure_type_gets_ai_translations_when_missing(): void
    {
        $this->mockGemini(['en' => 'Mast', 'fr' => 'Mât', 'de' => 'Mast']);

        $structureType = StructureType::create(['name' => ['nl' => 'Mast']]);
        $structureType->refresh();

        $this->assertSame('Mast', $structureType->getTranslation('name', 'en'));
        $this->assertSame('Mât', $structureType->getTranslation('name', 'fr'));
    }

    // ── Structure.info ───────────────────────────────────────────────────────

    public function test_structure_info_gets_ai_translations_when_missing(): void
    {
        $this->mockGemini(['en' => 'High steel mast', 'fr' => 'Mât en acier élevé', 'de' => 'Hoher Stahlmast']);

        $structureType = StructureType::factory()->create();
        $structure     = Structure::create([
            'structure_type_id'  => $structureType->id,
            'created_by_user_id' => null,
            'height'             => 800,
            'info'               => ['nl' => 'Hoge stalen mast'],
        ]);
        $structure->refresh();

        $this->assertSame('High steel mast', $structure->getTranslation('info', 'en'));
        $this->assertSame('Hoher Stahlmast', $structure->getTranslation('info', 'de'));
    }
}
