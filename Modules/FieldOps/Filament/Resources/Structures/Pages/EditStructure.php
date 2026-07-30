<?php

namespace Modules\FieldOps\Filament\Resources\Structures\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\StructureResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;

class EditStructure extends EditRecord
{
    protected static string $resource = StructureResource::class;

    // See ViewStructure::getResourceBreadcrumbs() / FieldOpsBreadcrumbs docblock.
    public function getResourceBreadcrumbs(): array
    {
        return FieldOpsBreadcrumbs::structureAncestors(
            $this->getRecord(),
            request()->integer('via_terrain') ?: null,
        );
    }

    // EditRecord::fillForm() fills the form from $record->attributesToArray(),
    // which bypasses Spatie HasTranslations::getAttributeValue() and returns
    // the raw {nl, en, fr, de} JSON object for `info` instead of the current
    // locale's string — without this, the form field renders "[object Object]".
    // Same fix already applied in Terrains/Pages/EditTerrain.php (CLA-269).
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $locale = app()->getLocale();

        if (isset($data['info']) && is_array($data['info'])) {
            $data['info'] = $this->getRecord()->getTranslation('info', $locale, false)
                ?? ($data['info'][$locale] ?? null)
                ?? ($data['info']['nl'] ?? null)
                ?? ($data['info']['en'] ?? null)
                ?? reset($data['info'])
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
