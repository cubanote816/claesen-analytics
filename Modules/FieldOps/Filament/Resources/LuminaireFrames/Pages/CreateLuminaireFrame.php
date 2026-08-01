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

    // See ViewTerrain::getResourceBreadcrumbs() / FieldOpsBreadcrumbs docblock.
    // structure_ids is the same query param LuminaireFramesRelationManager's
    // "Create" action already sends (LuminaireFrameResource::form() reads it
    // too, via contextualStructureIds() — not reused here since it's
    // protected on the Resource class, not this Page).
    public function getResourceBreadcrumbs(): array
    {
        $structureIds = request()->input('structure_ids');
        $structureId = is_array($structureIds) ? (int) Arr::first(array_filter($structureIds)) : null;

        return FieldOpsBreadcrumbs::luminaireFrameAncestorsForStructure(
            $structureId ? Structure::find($structureId) : null,
        );
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }
}
