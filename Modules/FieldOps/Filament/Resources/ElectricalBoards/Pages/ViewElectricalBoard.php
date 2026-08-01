<?php

namespace Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Models\Complex;

class ViewElectricalBoard extends ViewRecord
{
    protected static string $resource = ElectricalBoardResource::class;

    // See ViewTerrain::getResourceBreadcrumbs() / FieldOpsBreadcrumbs docblock.
    // via_structure/via_terrain/via_complex are forwarded by each of the 3
    // ElectricalBoardsRelationManager variants' recordUrl() (Complex/Terrain/
    // Structure's own "Electrical boards" tab) — absent when reached from the
    // flat "Electrical boards" sidebar index, where there's no parent to show.
    public function getResourceBreadcrumbs(): array
    {
        $structureId = request()->integer('via_structure') ?: null;
        $terrainId = request()->integer('via_terrain') ?: null;
        $complexId = request()->integer('via_complex') ?: null;

        return FieldOpsBreadcrumbs::electricalBoardAncestors(
            $structureId ? Structure::find($structureId) : null,
            $terrainId ? Terrain::find($terrainId) : null,
            $complexId ? Complex::find($complexId) : null,
            $terrainId,
        );
    }

    // See ViewFoClient::getTitle() for why this skips Filament's "View :label" wrapper.
    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scheduleMaintenance')
                ->label(__('fieldops::resource.electrical_boards.actions.schedule_maintenance'))
                ->icon('heroicon-m-clipboard-document-check')
                ->color('primary')
                ->url(fn (): string => FoMaintenanceWorkOrderResource::getUrl('create', array_filter([
                    'maintainable_type' => ElectricalBoard::class,
                    'maintainable_id' => $this->record->id,
                    'via_structure' => request()->integer('via_structure') ?: null,
                    'via_terrain' => request()->integer('via_terrain') ?: null,
                    'via_complex' => request()->integer('via_complex') ?: null,
                ]))),
            EditAction::make(),
        ];
    }
}
