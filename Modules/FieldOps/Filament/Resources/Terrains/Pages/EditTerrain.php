<?php

namespace Modules\FieldOps\Filament\Resources\Terrains\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\FieldOps\Filament\Resources\TerrainResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;

class EditTerrain extends EditRecord
{
    protected static string $resource = TerrainResource::class;

    public function getRelationManagers(): array
    {
        return [];
    }

    // See ViewTerrain::getResourceBreadcrumbs() / FieldOpsBreadcrumbs docblock.
    public function getResourceBreadcrumbs(): array
    {
        return FieldOpsBreadcrumbs::terrainAncestors($this->getRecord());
    }

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

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->getRecordTitle();
    }

    public function getBreadcrumb(): string
    {
        return (string) $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
            DeleteAction::make(),
        ];
    }
}
