<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Filament\Resources\Structures\Pages\ViewStructure;
use Tests\TestCase;

class StructureTerrainComplexTest extends TestCase
{
    use RefreshDatabase;

    public function test_terrain_complex_id_comes_from_the_attached_terrains(): void
    {
        $structure = Structure::factory()->create();
        $complex = Complex::factory()->create();
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);

        $structure->terrains()->attach($terrain->id);

        self::assertSame($complex->id, $structure->terrainComplexId());
    }

    public function test_terrain_complex_id_is_null_when_no_terrains_are_attached(): void
    {
        $structure = Structure::factory()->create();

        self::assertNull($structure->terrainComplexId());
    }

    public function test_attach_query_only_returns_terrains_from_the_same_complex(): void
    {
        $complexA = Complex::factory()->create();
        $complexB = Complex::factory()->create();
        $terrainA1 = Terrain::factory()->create(['complex_id' => $complexA->id]);
        $terrainA2 = Terrain::factory()->create(['complex_id' => $complexA->id]);
        Terrain::factory()->create(['complex_id' => $complexB->id]);

        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrainA1->id);

        $page = app(ViewStructure::class);
        $reflectedRecord = new \ReflectionProperty($page, 'record');
        $reflectedRecord->setAccessible(true);
        $reflectedRecord->setValue($page, $structure);

        $method = new \ReflectionMethod($page, 'terrainAttachQuery');
        $method->setAccessible(true);

        $query = $method->invoke($page);
        $terrainIds = $query->pluck('id')->all();

        self::assertContains($terrainA1->id, $terrainIds);
        self::assertContains($terrainA2->id, $terrainIds);
        self::assertCount(2, $terrainIds);
    }
}
