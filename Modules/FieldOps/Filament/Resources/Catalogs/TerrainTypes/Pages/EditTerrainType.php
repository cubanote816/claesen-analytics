<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\TerrainTypes\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\Catalogs\TerrainTypeResource;

class EditTerrainType extends EditRecord
{
    protected static string $resource = TerrainTypeResource::class;

    // EditRecord::fillForm() fills the form from $record->attributesToArray(),
    // which bypasses Spatie HasTranslations::getAttributeValue() and returns
    // the raw {nl, en, fr, de} JSON object for `type` instead of the current
    // locale's string — without this, the form field renders "[object Object]".
    // Same fix already applied in Terrains/Pages/EditTerrain.php for `name`.
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $locale = app()->getLocale();

        if (isset($data['type']) && is_array($data['type'])) {
            $data['type'] = $this->getRecord()->getTranslation('type', $locale, false)
                ?? ($data['type'][$locale] ?? null)
                ?? ($data['type']['nl'] ?? null)
                ?? ($data['type']['en'] ?? null)
                ?? reset($data['type'])
                ?? null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [RestoreAction::make(), DeleteAction::make()];
    }
}
