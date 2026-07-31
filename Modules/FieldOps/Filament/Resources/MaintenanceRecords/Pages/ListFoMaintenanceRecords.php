<?php

namespace Modules\FieldOps\Filament\Resources\MaintenanceRecords\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;
use Modules\FieldOps\Models\Luminaire;

class ListFoMaintenanceRecords extends ListRecords
{
    protected static string $resource = FoMaintenanceRecordResource::class;

    public ?int $luminaireId = null;

    public ?int $luminairePositionId = null;

    // See ViewTerrain::getResourceBreadcrumbs() / FieldOpsBreadcrumbs docblock.
    // "View history" only ever links here scoped to a Luminaire (luminaire/
    // position query params — LuminaireResource/LuminaireFrameResource build
    // maintenanceIndexUrl this way, never for an ElectricalBoard) — falls
    // back to Filament's default breadcrumb when reached without that
    // context (direct URL, or the global "Maintenance Records" sidebar-less
    // entry point).
    public function getResourceBreadcrumbs(): array
    {
        $luminaireId = request()->integer('luminaire') ?: null;

        if ($luminaireId === null) {
            return [];
        }

        $luminaire = Luminaire::find($luminaireId);

        if (! $luminaire) {
            return [];
        }

        return FieldOpsBreadcrumbs::luminaireTrail(
            $luminaire,
            request()->integer('via_structure') ?: null,
            request()->integer('via_terrain') ?: null,
        );
    }

    public function mount(): void
    {
        $this->luminaireId = request()->integer('luminaire') ?: null;
        $this->luminairePositionId = request()->integer('position') ?: null;

        if ($this->luminairePositionId === null && $this->luminaireId !== null) {
            $this->luminairePositionId = Luminaire::query()
                ->whereKey($this->luminaireId)
                ->value('luminaire_position_id');
        }

        parent::mount();
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return parent::getTableQuery()?->when($this->luminairePositionId, fn (Builder $query, int $positionId) => $query
            ->where('luminaire_position_id', $positionId))
            ->when(! $this->luminairePositionId && $this->luminaireId, fn (Builder $query) => $query
                ->where('maintainable_type', Luminaire::class)
                ->where('maintainable_id', $this->luminaireId));
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
        ];
    }
}
