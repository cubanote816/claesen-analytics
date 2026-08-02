<?php

namespace Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;
use Modules\FieldOps\Models\Structure;

class CreateLuminaireFrame extends CreateRecord
{
    protected static string $resource = LuminaireFrameResource::class;

    public ?int $contextStructureId = null;

    public function mount(): void
    {
        parent::mount();

        // Captured once here, not re-read from request() later — Livewire's
        // ->call() action invocations (e.g. the "create" button) don't
        // reliably see the original page load's query string the way a
        // fresh request()->input() read during mount()/initial render does.
        // Confirmed empirically: getRedirectUrlParameters() reading
        // request()->input('structure_ids') directly returned null even
        // with Livewire::withQueryParams() wired up, while reading this
        // property (set here, during mount) worked correctly.
        $structureIds = request()->input('structure_ids');
        $ids = is_array($structureIds) ? array_values(array_filter($structureIds, fn ($value) => $value !== null && $value !== '')) : [];
        $this->contextStructureId = $ids !== [] ? (int) Arr::first($ids) : null;
    }

    // See ViewTerrain::getResourceBreadcrumbs() / FieldOpsBreadcrumbs docblock.
    // structure_ids is the same query param LuminaireFramesRelationManager's
    // "Create" action already sends (LuminaireFrameResource::form() reads it
    // too, via contextualStructureIds() — not reused here since it's
    // protected on the Resource class, not this Page).
    public function getResourceBreadcrumbs(): array
    {
        return FieldOpsBreadcrumbs::luminaireFrameAncestorsForStructure(
            $this->contextStructureId ? Structure::find($this->contextStructureId) : null,
        );
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    // Filament's default post-create redirect goes to the new record's View
    // page with no extra query params — without via_structure the frame's
    // breadcrumb falls back to LuminaireFrame::resolveStructure()'s
    // deterministic "lowest id" among its attached structures, which can
    // silently diverge from the structure the user actually created it
    // under whenever a frame has more than one. Forward the same context
    // getResourceBreadcrumbs() uses.
    protected function getRedirectUrlParameters(): array
    {
        return array_filter(['via_structure' => $this->contextStructureId]);
    }
}
