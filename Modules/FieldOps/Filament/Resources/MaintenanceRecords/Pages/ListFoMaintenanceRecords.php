<?php

namespace Modules\FieldOps\Filament\Resources\MaintenanceRecords\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Models\Luminaire;

class ListFoMaintenanceRecords extends ListRecords
{
    protected static string $resource = FoMaintenanceRecordResource::class;

    public ?int $luminaireId = null;

    public function mount(): void
    {
        $this->luminaireId = request()->integer('luminaire') ?: null;

        parent::mount();
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return parent::getTableQuery()?->when($this->luminaireId, fn (Builder $query, int $luminaireId) => $query
            ->where('maintainable_type', Luminaire::class)
            ->where('maintainable_id', $luminaireId));
    }

    protected function getHeaderActions(): array
    {
        $luminaireId = $this->luminaireId;

        return [
            Action::make('backToLuminaire')
                ->label(__('fieldops::resource.luminaires.actions.back_to_luminaire'))
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->visible($luminaireId !== null)
                ->url($luminaireId ? LuminaireResource::getUrl('view', ['record' => $luminaireId]) : null),
            CreateAction::make()
                ->url($luminaireId ? FoMaintenanceRecordResource::getUrl('create', [
                    'maintainable_type' => Luminaire::class,
                    'maintainable_id' => $luminaireId,
                    'return_luminaire' => $luminaireId,
                ]) : null),
        ];
    }
}
