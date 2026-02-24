<?php

namespace App\Filament\Clusters\Website\Resources\ProjectResource\Pages;

use App\Filament\Clusters\Website\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        // Text Hydration (Required because Translatable trait is not used on this Page)
        $translatableAttributes = $record->translatable ?? [];
        $locale = app()->getLocale();

        foreach ($translatableAttributes as $attribute) {
            if (isset($data[$attribute]) && is_array($data[$attribute])) {
                $data[$attribute] = $record->getTranslation($attribute, $locale, false) ?? $data[$attribute];
            }
        }

        return $data;
    }


    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
