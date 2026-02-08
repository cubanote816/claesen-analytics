<?php

namespace App\Filament\Clusters\Website\Resources\PageResource\Pages;

use App\Filament\Clusters\Website\Resources\PageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

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
