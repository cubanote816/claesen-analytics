<?php

namespace Modules\FieldOps\Filament\Resources\Luminaires\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;
use Modules\FieldOps\Models\LuminaireType;
use Modules\FieldOps\Models\Structure;

class CreateLuminaire extends CreateRecord
{
    protected static string $resource = LuminaireResource::class;

    public ?int $contextStructureId = null;

    public ?int $contextTerrainId = null;

    public function mount(): void
    {
        parent::mount();

        // Captured once here, not re-read from request() later — see
        // CreateLuminaireFrame::mount() for why (Livewire's ->call() action
        // invocations don't reliably see the original page load's query
        // string the way a fresh read during mount()/initial render does).
        $this->contextStructureId = request()->integer('via_structure') ?: null;
        $this->contextTerrainId = request()->integer('via_terrain') ?: null;
    }

    // See FieldOpsBreadcrumbs::luminaireAncestorsForStructure() docblock — no
    // current caller sends via_structure/via_terrain here (Create Luminaire's
    // only wired entry point today is a modal reusing this same form, not
    // this page), kept for whenever one does; falls back to a bare
    // "Luminaires" label without it, same as every other unresolved-context
    // case in this class.
    public function getResourceBreadcrumbs(): array
    {
        return FieldOpsBreadcrumbs::luminaireAncestorsForStructure(
            $this->contextStructureId ? Structure::find($this->contextStructureId) : null,
            $this->contextTerrainId,
        );
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['luminaire_subgroup_id'] = isset($data['luminaire_type_id'])
            ? LuminaireType::find($data['luminaire_type_id'])?->luminaire_subgroup_id
            : null;
        $data['serial_number'] = LuminaireResource::resolveSerialNumber($data['serial_number'] ?? null);
        $data['created_by_user_id'] = auth()->id();
        $data['position_version'] = 1;
        $data['position_source'] = 'backoffice';
        $data['position_verified_at'] = null;
        $data['position_verified_by_user_id'] = null;

        return $data;
    }

    // See CreateLuminaireFrame::getRedirectUrlParameters() — same reasoning,
    // forwards whatever via context was actually present (today, none is —
    // kept for whenever a real caller sends one).
    protected function getRedirectUrlParameters(): array
    {
        return array_filter([
            'via_structure' => $this->contextStructureId,
            'via_terrain' => $this->contextTerrainId,
        ]);
    }
}
