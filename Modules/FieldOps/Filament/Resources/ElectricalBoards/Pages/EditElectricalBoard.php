<?php

namespace Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;

class EditElectricalBoard extends EditRecord
{
    protected static string $resource = ElectricalBoardResource::class;

    // EditRecord::fillForm() fills the form from $record->attributesToArray(),
    // which bypasses Spatie HasTranslations::getAttributeValue() and returns
    // the raw {nl, en, fr, de} JSON object for `location_description` instead
    // of the current locale's string — without this, the form field renders
    // "[object Object]". Same fix already applied in Terrains/Pages/EditTerrain.php (CLA-269).
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $locale = app()->getLocale();

        if (isset($data['location_description']) && is_array($data['location_description'])) {
            $data['location_description'] = $this->getRecord()->getTranslation('location_description', $locale, false)
                ?? ($data['location_description'][$locale] ?? null)
                ?? ($data['location_description']['nl'] ?? null)
                ?? ($data['location_description']['en'] ?? null)
                ?? reset($data['location_description'])
                ?? null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
            DeleteAction::make(),
        ];
    }
}
