<?php

namespace App\Filament\Clusters\Website\Resources\PageResource\Pages;

use App\Filament\Clusters\Website\Resources\PageResource;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    use Translatable;

    protected function getHeaderActions(): array
    {
        return [
            \LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher::make(),
        ];
    }
}
