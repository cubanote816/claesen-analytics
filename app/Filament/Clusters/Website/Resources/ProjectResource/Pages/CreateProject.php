<?php

namespace App\Filament\Clusters\Website\Resources\ProjectResource\Pages;

use App\Filament\Clusters\Website\Resources\ProjectResource;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    use Translatable;

    protected function getHeaderActions(): array
    {
        return [
            \LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher::make(),
        ];
    }
}
