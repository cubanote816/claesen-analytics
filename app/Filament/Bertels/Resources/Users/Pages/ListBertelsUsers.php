<?php

declare(strict_types=1);

namespace App\Filament\Bertels\Resources\Users\Pages;

use App\Filament\Bertels\Resources\Users\BertelsUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBertelsUsers extends ListRecords
{
    protected static string $resource = BertelsUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
