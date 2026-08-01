<?php

namespace Modules\FieldOps\Filament\Resources\Terrains\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\TerrainResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;
use Modules\FieldOps\Models\Complex;

class CreateTerrain extends CreateRecord
{
    protected static string $resource = TerrainResource::class;

    public ?array $structureIds = null;

    // See ViewTerrain::getResourceBreadcrumbs() / FieldOpsBreadcrumbs docblock.
    // complex_id is the same query param TerrainsRelationManager's "Create
    // terrain" action already sends (required — a Terrain always belongs to
    // exactly one Complex, unlike the M:N levels below it).
    public function getResourceBreadcrumbs(): array
    {
        $complexId = request()->integer('complex_id') ?: null;

        return FieldOpsBreadcrumbs::terrainAncestorsForComplex(
            $complexId ? Complex::find($complexId) : null,
        );
    }

    public function mount(): void
    {
        parent::mount();

        $structureIds = request()->input('structure_ids');
        $this->structureIds = is_array($structureIds)
            ? array_values(array_filter($structureIds, fn ($value) => $value !== null && $value !== ''))
            : null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record && $this->structureIds !== null) {
            $this->record->structures()->syncWithoutDetaching($this->structureIds);
        }
    }
}
