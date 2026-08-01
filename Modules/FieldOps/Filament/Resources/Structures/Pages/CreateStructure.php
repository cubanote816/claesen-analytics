<?php

namespace Modules\FieldOps\Filament\Resources\Structures\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Modules\FieldOps\Filament\Resources\StructureResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Services\StructureProximityService;
use Livewire\Attributes\On;

class CreateStructure extends CreateRecord
{
    protected static string $resource = StructureResource::class;

    public ?array $terrainIds = null;

    // See ViewTerrain::getResourceBreadcrumbs() / FieldOpsBreadcrumbs docblock.
    // terrain_ids can carry more than one (Structure<->Terrain is M:N), same
    // query param mount() already reads to attach on create — the first one
    // is enough for the breadcrumb, same "deterministic first/lowest wins"
    // spirit as Structure::resolveTerrain()'s fallback.
    public function getResourceBreadcrumbs(): array
    {
        $terrainIds = request()->input('terrain_ids');
        $terrainId = is_array($terrainIds) ? (int) Arr::first(array_filter($terrainIds)) : null;

        return FieldOpsBreadcrumbs::structureAncestorsForTerrain(
            $terrainId ? Terrain::find($terrainId) : null,
        );
    }
    public ?array $lastSelectedLocation = null;
    public ?array $pendingFormData = null;
    public ?array $proximityMatch = null;
    public ?int $proximityBypassedStructureId = null;

    public function mount(): void
    {
        parent::mount();

        $terrainIds = request()->input('terrain_ids');
        $this->terrainIds = is_array($terrainIds)
            ? array_values(array_filter(Arr::wrap($terrainIds), fn ($value) => $value !== null && $value !== ''))
            : null;

        $this->syncTerrainIdsToForm();
        $this->rememberSelectedLocation();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->terrainIds = array_values(array_filter(
            Arr::wrap($data['terrain_ids'] ?? $this->terrainIds ?? []),
            fn ($value) => $value !== null && $value !== '',
        ));

        $this->pendingFormData = [
            ...$data,
            'terrain_ids' => $this->terrainIds,
        ];
        $this->proximityMatch = null;

        unset($data['terrain_ids']);
        $data['created_by_user_id'] = auth()->id();

        $nearbyStructure = $this->detectNearbyStructure($data);

        if (
            $nearbyStructure !== null &&
            $this->proximityBypassedStructureId !== $nearbyStructure['id']
        ) {
            $this->proximityMatch = $nearbyStructure;
            $this->dispatch('fieldops-structure-proximity-warning-shown');
            $this->restorePendingFormData();
            $this->halt(shouldRollbackDatabaseTransaction: true);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record && $this->terrainIds !== null) {
            $this->record->terrains()->syncWithoutDetaching($this->terrainIds);
        }

        $this->dispatch('fieldops-structure-location-clear');
        $this->pendingFormData = null;
        $this->proximityMatch = null;
        $this->proximityBypassedStructureId = null;
    }

    #[On('fieldops-create-structure-create-anyway')]
    public function createAnyway(int $structureId): void
    {
        $this->proximityBypassedStructureId = $structureId;
        $this->restorePendingFormData();

        $this->create();
    }

    public function attachToDetectedStructure(int $structureId): void
    {
        $structure = Structure::query()->findOrFail($structureId);
        $terrainIds = array_values(array_filter(
            Arr::wrap($this->terrainIds ?? []),
            fn ($value) => $value !== null && $value !== '',
        ));

        if ($terrainIds !== []) {
            $structure->terrains()->syncWithoutDetaching($terrainIds);
        }

        Notification::make()
            ->title(__('fieldops::resource.structures.validation.proximity_attach_success', [
                'label' => StructureResource::getRecordTitle($structure),
            ]))
            ->success()
            ->send();

        $this->dispatch('fieldops-structure-location-clear');
        $this->redirect(StructureResource::getUrl('view', ['record' => $structure]));
    }

    public function updatedTerrainIds(): void
    {
        $this->syncTerrainIdsToForm();
    }

    protected function syncTerrainIdsToForm(): void
    {
        if (! isset($this->form)) {
            return;
        }

        $this->form->rawState([
            ...$this->form->getRawState(),
            'terrain_ids' => $this->terrainIds ?? [],
        ]);
    }

    protected function restorePendingFormData(): void
    {
        if ($this->pendingFormData === null) {
            return;
        }

        $this->data = [
            ...($this->data ?? []),
            ...$this->pendingFormData,
        ];

        if (isset($this->form)) {
            $this->form->fill($this->pendingFormData);
        }

        $lat = isset($this->pendingFormData['lat']) ? (float) $this->pendingFormData['lat'] : null;
        $lng = isset($this->pendingFormData['lng']) ? (float) $this->pendingFormData['lng'] : null;

        if ($lat !== null && $lng !== null) {
            $this->dispatch('fieldops-structure-location-restored', [
                'lat' => $lat,
                'lng' => $lng,
            ]);
        }
    }

    public function updated(string $name, mixed $value): void
    {
        if ($name === 'terrainIds') {
            $this->syncTerrainIdsToForm();
            return;
        }

        if (str_starts_with($name, 'data.lat') || str_starts_with($name, 'data.lng')) {
            $this->rememberSelectedLocation();
        }
    }

    protected function getRedirectUrl(): string
    {
        return StructureResource::getUrl('view', ['record' => $this->record]);
    }

    protected function detectNearbyStructure(array $data): ?array
    {
        $match = app(StructureProximityService::class)->findNearbyStructure(
            $this->terrainIds ?? [],
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
            (int) config('fieldops.structure_proximity_warning_meters', 10),
        );

        if ($match === null) {
            return null;
        }

        return [
            'id' => $match['structure']->getKey(),
            'label' => StructureResource::getRecordTitle($match['structure']),
            'distance_meters' => $match['distance_meters'],
            'radius_meters' => $match['radius_meters'],
        ];
    }

    protected function rememberSelectedLocation(): void
    {
        $lat = isset($this->data['lat']) ? (float) $this->data['lat'] : null;
        $lng = isset($this->data['lng']) ? (float) $this->data['lng'] : null;

        if ($lat === null || $lng === null) {
            return;
        }

        $this->lastSelectedLocation = [
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    protected function syncSelectedLocationToForm(): void
    {
        if ($this->lastSelectedLocation === null || ! isset($this->form)) {
            return;
        }

        $locationKey = $this->selectedLocationKey($this->lastSelectedLocation);
        $currentKey = $this->selectedLocationKey([
            'lat' => $this->data['lat'] ?? null,
            'lng' => $this->data['lng'] ?? null,
        ]);

        if ($locationKey === null || $locationKey === $currentKey) {
            return;
        }

        $this->data = [
            ...($this->data ?? []),
            'lat' => $this->lastSelectedLocation['lat'],
            'lng' => $this->lastSelectedLocation['lng'],
            'map_center_lat' => $this->lastSelectedLocation['lat'],
            'map_center_lng' => $this->lastSelectedLocation['lng'],
        ];

        $this->form->rawState([
            ...$this->form->getRawState(),
            'lat' => $this->lastSelectedLocation['lat'],
            'lng' => $this->lastSelectedLocation['lng'],
            'map_center_lat' => $this->lastSelectedLocation['lat'],
            'map_center_lng' => $this->lastSelectedLocation['lng'],
        ]);

        $this->dispatch('fieldops-structure-location-restored', [
            'lat' => $this->lastSelectedLocation['lat'],
            'lng' => $this->lastSelectedLocation['lng'],
        ]);
    }

    protected function selectedLocationKey(?array $location): ?string
    {
        if ($location === null || ! isset($location['lat'], $location['lng'])) {
            return null;
        }

        return number_format((float) $location['lat'], 6, '.', '').','.number_format((float) $location['lng'], 6, '.', '');
    }
}
