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

    // See FieldOpsBreadcrumbs::luminaireAncestorsForStructure() docblock — no
    // current caller sends via_structure/via_terrain here (Create Luminaire's
    // only wired entry point today is a modal reusing this same form, not
    // this page), kept for whenever one does; falls back to a bare
    // "Luminaires" label without it, same as every other unresolved-context
    // case in this class.
    public function getResourceBreadcrumbs(): array
    {
        $structureId = request()->integer('via_structure') ?: null;

        return FieldOpsBreadcrumbs::luminaireAncestorsForStructure(
            $structureId ? Structure::find($structureId) : null,
            request()->integer('via_terrain') ?: null,
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
}
