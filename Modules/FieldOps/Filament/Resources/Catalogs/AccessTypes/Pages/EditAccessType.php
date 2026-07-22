<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\AccessTypes\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\Catalogs\AccessTypeResource;

class EditAccessType extends EditRecord
{
    protected static string $resource = AccessTypeResource::class;

    // EditRecord::fillForm() fills the form from $record->attributesToArray(),
    // which bypasses Spatie HasTranslations::getAttributeValue() and returns
    // the raw {nl, en, fr, de} JSON object for `name` instead of the current
    // locale's string — without this, the form field renders "[object Object]".
    // Same fix already applied in Terrains/Pages/EditTerrain.php (CLA-269).
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $locale = app()->getLocale();

        if (isset($data['name']) && is_array($data['name'])) {
            $data['name'] = $this->getRecord()->getTranslation('name', $locale, false)
                ?? ($data['name'][$locale] ?? null)
                ?? ($data['name']['nl'] ?? null)
                ?? ($data['name']['en'] ?? null)
                ?? reset($data['name'])
                ?? null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [RestoreAction::make(), DeleteAction::make()];
    }
}
