<?php

namespace App\Filament\Clusters\Website\Resources\ProjectResource\Pages;

use App\Filament\Clusters\Website\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

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
