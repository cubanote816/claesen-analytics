<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Resources\AnnouncementResource\Pages;

use App\Filament\Clusters\Website\Resources\AnnouncementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    /**
     * F3/CLA-469: same "text hydration" pattern as EditProject/EditTerrain/etc.
     * (CLA-269/274) — EditRecord::fillForm() uses attributesToArray(), which
     * bypasses Spatie's translation accessor for HasTranslations attributes.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
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
