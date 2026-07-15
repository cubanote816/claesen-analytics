<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Unit;

use Filament\Tables\Table;
use Filament\Tables\Columns\ViewColumn;
use Modules\FieldOps\Filament\Resources\Structures\RelationManagers\TerrainsRelationManager;
use Tests\TestCase;

class TerrainsRelationManagerTest extends TestCase
{
    public function test_detach_action_is_configured_as_a_view_column(): void
    {
        $relationManager = app(TerrainsRelationManager::class);
        $table = $relationManager->table(Table::make($relationManager));

        $columns = $table->getColumns();

        self::assertCount(4, $columns);

        $detachColumn = collect($columns)->first(fn ($column) => $column->getName() === 'detach_action');

        self::assertNotNull($detachColumn);
        self::assertInstanceOf(ViewColumn::class, $detachColumn);
    }
}
