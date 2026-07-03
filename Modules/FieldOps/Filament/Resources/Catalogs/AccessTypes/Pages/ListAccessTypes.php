<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\AccessTypes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\Catalogs\AccessTypeResource;

class ListAccessTypes extends ListRecords
{
    protected static string $resource = AccessTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
