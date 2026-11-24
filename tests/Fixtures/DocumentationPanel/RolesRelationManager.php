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

    /** Set through RelationManager::make(['showExtra' => true]): the table depends on it. */
    public bool $showExtra = false;

    public function table(Table $table): Table
    {
        return $table->recordActions([Action::make($this->showExtra ? 'inspect_extra' : 'inspect_default')]);
    }
}
