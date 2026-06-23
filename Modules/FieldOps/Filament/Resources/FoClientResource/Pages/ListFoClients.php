<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\FoClientResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\FieldOps\Filament\Resources\FoClientResource;

class ListFoClients extends ListRecords
{
    protected static string $resource = FoClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
