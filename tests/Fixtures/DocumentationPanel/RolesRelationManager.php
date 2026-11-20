<?php

declare(strict_types=1);

namespace Tests\Fixtures\DocumentationPanel;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/** CLA-728 fixture: registered through RelationManager::make(), a configuration object. */
class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    public function table(Table $table): Table
    {
        return $table->recordActions([Action::make('inspect')]);
    }
}
