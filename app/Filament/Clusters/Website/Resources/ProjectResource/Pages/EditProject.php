<?php

namespace App\Filament\Clusters\Website\Resources\ProjectResource\Pages;

use App\Filament\Clusters\Website\Actions\PublishSiteNowAction;
use App\Filament\Clusters\Website\Concerns\ShowsSitePublicationState;
use App\Filament\Clusters\Website\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    use ShowsSitePublicationState;

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


    /**
     * The trait contributes the publish action and the status widget. Delete is written out
     * explicitly rather than left to the parent: overriding getHeaderActions() to add one
     * action is exactly how the other one gets dropped without anyone noticing.
     */
    protected function getHeaderActions(): array
    {
        return [
            PublishSiteNowAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
