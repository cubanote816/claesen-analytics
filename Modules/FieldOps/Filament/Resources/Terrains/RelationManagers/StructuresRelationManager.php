<?php

namespace Modules\FieldOps\Filament\Resources\Terrains\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\FieldOps\Filament\Resources\StructureResource;

/**
 * Terrain belongsToMany Structure (fo_structure_terrain) — same pivot as
 * Structures\RelationManagers\TerrainsRelationManager, seen from the other side.
 * The terrain page also exposes a create shortcut here so a new structure can
 * be created prelinked to the current terrain.
 */
class StructuresRelationManager extends RelationManager
{
    protected static string $relationship = 'structures';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fieldops::resource.structures.plural_label');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->structures()->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->recordUrl(fn ($record) => StructureResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('structureType.name')
                    ->label(__('fieldops::resource.structures.fields.structure_type'))
                    ->getStateUsing(fn ($record) => $record->structureType?->getTranslation('name', app()->getLocale(), false)
                        ?: $record->structureType?->getTranslation('name', 'nl', false))
                    ->badge()
                    ->color('info'),
                TextColumn::make('height')
                    ->label(__('fieldops::resource.structures.fields.height'))
                    ->suffix(' cm'),
            ])
            ->headerActions([
                Action::make('createStructure')
                    ->label(__('fieldops::resource.structures.actions.create'))
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->color('primary')
                    ->url(StructureResource::getUrl('create', [
                        'terrain_ids' => [$this->getOwnerRecord()->getKey()],
                    ])),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
