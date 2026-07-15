<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Unit;

use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Filament\Resources\Terrains\RelationManagers\StructuresRelationManager;
use Modules\FieldOps\Models\Terrain;
use Tests\TestCase;

class StructuresRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_structure_header_action_prefills_the_current_terrain(): void
    {
        $terrain = Terrain::factory()->create();

        $relationManager = app(StructuresRelationManager::class);
        $reflectedOwnerRecord = new \ReflectionProperty($relationManager, 'ownerRecord');
        $reflectedOwnerRecord->setAccessible(true);
        $reflectedOwnerRecord->setValue($relationManager, $terrain);

        $table = $relationManager->table(Table::make($relationManager));
        $action = collect($table->getHeaderActions())
            ->first(fn ($headerAction) => $headerAction->getName() === 'createStructure');

        self::assertNotNull($action);
        self::assertSame(__('fieldops::resource.structures.actions.create'), $action->getLabel());

        $query = parse_url($action->getUrl() ?? '', PHP_URL_QUERY);
        parse_str((string) $query, $parameters);

        self::assertSame([(string) $terrain->id], array_map('strval', $parameters['terrain_ids'] ?? []));
    }
}
