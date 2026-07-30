<?php

namespace Modules\FieldOps\Filament\Resources\Structures\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Modules\FieldOps\Filament\Resources\StructureResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;
use Modules\FieldOps\Models\Terrain;

class ViewStructure extends ViewRecord
{
    protected static string $resource = StructureResource::class;

    // See ViewTerrain::getResourceBreadcrumbs() / FieldOpsBreadcrumbs docblock.
    // ?via_terrain= carries which terrain the user actually navigated through
    // (Structure<->Terrain is a real M:N) — see Structure::resolveTerrain().
    public function getResourceBreadcrumbs(): array
    {
        return FieldOpsBreadcrumbs::structureAncestors(
            $this->getRecord(),
            request()->integer('via_terrain') ?: null,
        );
    }

    // See ViewFoClient::getTitle() for why this skips Filament's "View :label" wrapper.
    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('attachTerrain')
                ->label(__('fieldops::resource.terrains.actions.attach'))
                ->button()
                ->icon('heroicon-m-link')
                ->color('gray')
                ->modalWidth('2xl')
                ->form([
                    Select::make('recordId')
                        ->label(__('fieldops::resource.terrains.model_label'))
                        ->searchable()
                        ->required()
                        ->getSearchResultsUsing(fn (string $search) => $this->terrainAttachQuery()
                            ->where('name->nl', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Terrain $terrain) => [
                                $terrain->id => $terrain->getTranslation('name', app()->getLocale(), false)
                                    ?: $terrain->getTranslation('name', 'nl', false),
                            ]))
                        ->getOptionLabelUsing(function ($value) {
                            $terrain = Terrain::find($value);

                            return $terrain
                                ? ($terrain->getTranslation('name', app()->getLocale(), false)
                                    ?: $terrain->getTranslation('name', 'nl', false))
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
                            ->all()),
                ])
                ->action(function (array $data): void {
                    $this->record->terrains()->syncWithoutDetaching([
                        $data['recordId'],
                    ]);

                    $this->dispatch('structure-terrains-updated');
                }),
            EditAction::make(),
        ];
    }

    protected function terrainAttachQuery(): Builder
    {
        $complexId = $this->record->terrainComplexId();

        return Terrain::query()->when(
            $complexId !== null,
            fn (Builder $query) => $query->where('complex_id', $complexId),
            fn (Builder $query) => $query->whereRaw('1 = 0'),
        );
    }
}
