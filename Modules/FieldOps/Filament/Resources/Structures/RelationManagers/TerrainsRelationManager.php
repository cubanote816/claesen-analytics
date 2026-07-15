<?php

namespace Modules\FieldOps\Filament\Resources\Structures\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Modules\FieldOps\Filament\Resources\TerrainResource;
use Modules\FieldOps\Models\Terrain;

/**
 * Structure belongsToMany Terrain (fo_structure_terrain) — a pole can sit on the
 * boundary of more than one field, so existing Terrains can be attached/detached.
 * Terrain creation is intentionally not exposed here because "Create terrain"
 * reads as a standalone entity and is too easy to confuse with a shared link.
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
            ->columns([
                TextColumn::make('name')
                    ->label(__('fieldops::resource.terrains.fields.name'))
                    ->getStateUsing(fn ($record) => $record->getTranslation('name', app()->getLocale(), false)
                        ?: $record->getTranslation('name', 'nl', false))
                    ->url(fn (Terrain $record) => TerrainResource::getUrl('view', ['record' => $record]))
                    ->searchable(),
                TextColumn::make('terrainType.type')
                    ->label(__('fieldops::resource.terrains.fields.terrain_type'))
                    ->getStateUsing(fn ($record) => $record->terrainType?->getTranslation('type', app()->getLocale(), false)
                        ?: $record->terrainType?->getTranslation('type', 'nl', false))
                    ->badge()
                    ->color('info'),
                TextColumn::make('complex.name')
                    ->label(__('fieldops::resource.terrains.fields.complex')),
                ViewColumn::make('detach_action')
                    ->label(__('fieldops::resource.terrains.actions.detach'))
                    ->view('fieldops::filament.tables.terrain-detach-action')
                    ->viewData(fn (Terrain $record) => [
                        'terrainId' => $record->getKey(),
                        'canDetach' => $this->getOwnerRecord()->terrains()->count() > 1,
                    ]),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label(__('fieldops::resource.terrains.actions.attach'))
                    ->button()
                    ->icon('heroicon-m-link')
                    ->color('gray')
                    ->modalWidth('2xl')
                    ->recordSelect(fn (Select $select) => $select
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => $this->terrainAttachQuery()
                            ->where('name->nl', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($terrain) => [$terrain->id => $terrain->getTranslation('name', app()->getLocale(), false) ?: $terrain->getTranslation('name', 'nl', false)]))
                        ->getOptionLabelUsing(function ($value) {
                            $terrain = Terrain::find($value);

                            return $terrain
                                ? ($terrain->getTranslation('name', app()->getLocale(), false) ?: $terrain->getTranslation('name', 'nl', false))
                                : null;
                        })
                        ->options(fn (): array => $this->terrainAttachQuery()
                            ->orderBy('name')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Terrain $terrain) => [
                                $terrain->id => $terrain->getTranslation('name', app()->getLocale(), false)
                                    ?: $terrain->getTranslation('name', 'nl', false),
                            ])
                            ->all())
                    )
                    ->action(function (array $data): void {
                        $this->getOwnerRecord()->terrains()->syncWithoutDetaching([
                            $data['recordId'],
                        ]);
                        $this->resetTable();
                    }),
            ]);
    }

    public function detachTerrain(int $terrainId): void
    {
        if ($this->getOwnerRecord()->terrains()->count() <= 1) {
            throw ValidationException::withMessages([
                'detach_action' => __('fieldops::resource.structures.validation.min_terrain'),
            ]);
        }

        $this->getOwnerRecord()->terrains()->detach($terrainId);
        $this->resetTable();
    }

    #[On('structure-terrains-updated')]
    public function refreshTerrainsTable(): void
    {
        $this->resetTable();
    }

    protected function terrainAttachQuery(): Builder
    {
        $complexId = $this->getOwnerRecord()->terrainComplexId();

        return Terrain::query()->when(
            $complexId !== null,
            fn (Builder $query) => $query->where('complex_id', $complexId),
            fn (Builder $query) => $query->whereRaw('1 = 0'),
        );
    }
}
