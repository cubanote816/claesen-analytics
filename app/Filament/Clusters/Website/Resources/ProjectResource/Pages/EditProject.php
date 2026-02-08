<?php

namespace App\Filament\Clusters\Website\Resources\ProjectResource\Pages;

use App\Filament\Clusters\Website\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    use \LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;
    use \LaraZeus\SpatieTranslatable\Resources\Concerns\HasActiveLocaleSwitcher;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            \LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher::make(),
        ];
    }
}
