<?php

namespace Modules\FieldOps\Filament\Resources\Terrains\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\FieldOps\Filament\Resources\StructureResource;
use Modules\FieldOps\Models\Structure;

/**
 * Terrain belongsToMany Structure (fo_structure_terrain) — same pivot as
 * Structures\RelationManagers\TerrainsRelationManager, seen from the other side.
 * Attach/detach of existing Structures, never create/edit (Structure has its own
 * resource and lifecycle).
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
                AttachAction::make()
                    ->recordSelect(fn (Select $select) => $select
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Structure::query()
                            ->where('id', 'like', "%{$search}%")
                            ->with('structureType')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Structure $structure) => [$structure->id => '#'.$structure->id.' — '.($structure->structureType?->getTranslation('name', app()->getLocale(), false) ?: $structure->structureType?->getTranslation('name', 'nl', false))]))
                        ->getOptionLabelUsing(function ($value) {
                            $structure = Structure::with('structureType')->find($value);

                            return $structure
                                ? '#'.$structure->id.' — '.($structure->structureType?->getTranslation('name', app()->getLocale(), false) ?: $structure->structureType?->getTranslation('name', 'nl', false))
                                : null;
                        })),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
