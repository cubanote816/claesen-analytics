<?php

namespace App\Filament\Clusters\Website\Resources\PageResource\Pages;

use App\Filament\Clusters\Website\Resources\PageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    use \LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;
    use \LaraZeus\SpatieTranslatable\Resources\Concerns\HasActiveLocaleSwitcher;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            \LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher::make(),
        ];
    }
}
