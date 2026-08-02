<?php

namespace Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages;

use Illuminate\Support\Arr;
use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;

class CreateElectricalBoard extends CreateRecord
{
    protected static string $resource = ElectricalBoardResource::class;

    public ?int $complexId = null;
    public ?array $structureIds = null;
    public ?array $terrainIds = null;

    // See FieldOpsBreadcrumbs::electricalBoardAncestors() docblock —
    // exactly one of these 3 query params is present depending on which of
    // Complex/Terrain/Structure's "Create electrical board" action was used.
    public function getResourceBreadcrumbs(): array
    {
        $structureIds = request()->input('structure_ids');
        $structureId = is_array($structureIds) ? (int) Arr::first(array_filter($structureIds)) : null;

        $terrainIds = request()->input('terrain_ids');
        $terrainId = is_array($terrainIds) ? (int) Arr::first(array_filter($terrainIds)) : null;

        $complexId = request()->integer('complex_id') ?: null;

        return FieldOpsBreadcrumbs::electricalBoardAncestors(
            $structureId ? Structure::find($structureId) : null,
            $terrainId ? Terrain::find($terrainId) : null,
            $complexId ? Complex::find($complexId) : null,
            $terrainId,
        );
    }

    public function mount(): void
    {
        parent::mount();

        $this->complexId = request()->integer('complex_id') ?: null;
        $structureIds = request()->input('structure_ids');
        $this->structureIds = is_array($structureIds)
            ? array_values(array_filter($structureIds, fn ($value) => $value !== null && $value !== ''))
            : null;
        $terrainIds = request()->input('terrain_ids');
        $this->terrainIds = is_array($terrainIds)
            ? array_values(array_filter(Arr::wrap($terrainIds), fn ($value) => $value !== null && $value !== ''))
            : null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    // Filament's default post-create redirect goes to the new record's View
    // page with NO extra query params — for ElectricalBoard specifically
    // that's not a minor nuance, it's a full context loss: unlike Structure/
    // LuminaireFrame (which have a deterministic "lowest id" fallback to at
    // least show SOMETHING), ElectricalBoard has no stored FK to any of
    // Complex/Terrain/Structure at all, purely M:N pivots — without these
    // params the breadcrumb collapses to a bare "Electrical Boards > #new >
    // View", losing the exact hierarchy the user just built the record
    // under. Forward the same via context getResourceBreadcrumbs() uses.
    protected function getRedirectUrlParameters(): array
    {
        return array_filter([
            'via_structure' => $this->structureIds[0] ?? null,
            'via_terrain' => $this->terrainIds[0] ?? null,
            'via_complex' => $this->complexId,
        ]);
    }

    protected function afterCreate(): void
    {
        if ($this->complexId) {
            $this->record?->complexes()->syncWithoutDetaching([$this->complexId]);
        }

        if ($this->structureIds !== null) {
            $this->record?->structures()->syncWithoutDetaching($this->structureIds);
        }

        if ($this->terrainIds !== null) {
            $this->record?->terrains()->syncWithoutDetaching($this->terrainIds);
        }
    }
}
