<?php

namespace Modules\FieldOps\Filament\Resources\Structures\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\FieldOps\Filament\Resources\TerrainResource;
use Modules\FieldOps\Models\Terrain;

/**
 * Structure belongsToMany Terrain (fo_structure_terrain) — a pole can sit on the
 * boundary of more than one field, so existing Terrains can be attached/detached.
 * When the structure is already anchored to a single complex, we also allow
 * creating a new Terrain in the same complex and linking it back here.
 */
class TerrainsRelationManager extends RelationManager
{
    protected static string $relationship = 'terrains';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fieldops::resource.terrains.plural_label');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->terrains()->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->recordUrl(fn ($record) => TerrainResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label(__('fieldops::resource.terrains.fields.name'))
                    ->getStateUsing(fn ($record) => $record->getTranslation('name', app()->getLocale(), false)
                        ?: $record->getTranslation('name', 'nl', false)),
                TextColumn::make('terrainType.type')
                    ->label(__('fieldops::resource.terrains.fields.terrain_type'))
                    ->getStateUsing(fn ($record) => $record->terrainType?->getTranslation('type', app()->getLocale(), false)
                        ?: $record->terrainType?->getTranslation('type', 'nl', false))
                    ->badge()
                    ->color('info'),
                TextColumn::make('complex.name')
                    ->label(__('fieldops::resource.terrains.fields.complex')),
            ])
            ->headerActions([
                Action::make('createTerrain')
                    ->label(__('fieldops::resource.terrains.actions.create'))
                    ->button()
                    ->icon('heroicon-m-plus')
                    ->color('primary')
                    ->visible(fn (): bool => $this->getCreateTerrainUrl() !== null)
                    ->url(fn (): ?string => $this->getCreateTerrainUrl()),
                AttachAction::make()
                    ->recordSelect(fn (Select $select) => $select
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Terrain::query()
                            ->where('name->nl', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($terrain) => [$terrain->id => $terrain->getTranslation('name', app()->getLocale(), false) ?: $terrain->getTranslation('name', 'nl', false)]))
                        ->getOptionLabelUsing(function ($value) {
                            $terrain = Terrain::find($value);

                            return $terrain
                                ? ($terrain->getTranslation('name', app()->getLocale(), false) ?: $terrain->getTranslation('name', 'nl', false))
                                : null;
                        })),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }

    protected function getCreateTerrainUrl(): ?string
    {
        $ownerRecord = $this->getOwnerRecord();
        $complexIds = $ownerRecord->terrains()
            ->distinct()
            ->pluck('complex_id')
            ->filter()
            ->values();

        if ($complexIds->count() !== 1) {
            return null;
        }

        return TerrainResource::getUrl('create', [
            'complex_id' => $complexIds->first(),
            'structure_ids' => [$ownerRecord->getKey()],
        ]);
    }
}
