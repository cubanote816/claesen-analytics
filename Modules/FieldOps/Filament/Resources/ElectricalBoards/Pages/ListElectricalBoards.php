<?php

namespace Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;

class ListElectricalBoards extends ListRecords
{
    protected static string $resource = ElectricalBoardResource::class;

    // No CreateAction here on purpose: an electrical board is never created
    // standalone from this flat (nav-hidden) index — only contextually, from
    // a Complex/Terrain/Structure's "Electrical boards" tab. See the guard
    // in CreateElectricalBoard::mount() for the server-side enforcement.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
