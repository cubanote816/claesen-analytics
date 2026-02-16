<?php

namespace App\Filament\Clusters\Website\Resources\ProjectResource\Pages;

use App\Filament\Clusters\Website\Resources\ProjectResource;
use Filament\Resources\Pages\CreateRecord;


class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
