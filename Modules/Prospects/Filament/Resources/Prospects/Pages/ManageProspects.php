<?php

namespace Modules\Prospects\Filament\Resources\Prospects\Pages;

use Modules\Prospects\Filament\Resources\Prospects\ProspectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProspects extends ManageRecords
{
    protected static string $resource = ProspectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
