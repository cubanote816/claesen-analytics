<?php

namespace Modules\FieldOps\Filament\Resources\Luminaires\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireType;

class EditLuminaire extends EditRecord
{
    protected static string $resource = LuminaireResource::class;

    // See ViewLuminaire::getResourceBreadcrumbs() / FieldOpsBreadcrumbs docblock.
    public function getResourceBreadcrumbs(): array
    {
        return FieldOpsBreadcrumbs::luminaireAncestors(
            $this->getRecord(),
            request()->integer('via_structure') ?: null,
            request()->integer('via_terrain') ?: null,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            RestoreAction::make(),
            DeleteAction::make(),
        ];
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

        // Pre-fill the Complex/Terrain/Structure cascade from the frame's current location
        // so editing an existing luminaire shows its real context instead of empty selects
        // (these three fields are dehydrated(false) — pure UI scaffolding, not DB columns).
        if (! empty($data['luminaire_frame_id'])) {
            $frame = LuminaireFrame::with('structures.terrains')->find($data['luminaire_frame_id']);
            $structure = $frame?->structures->first();
            $terrain = $structure?->terrains->first();

            $data['structure_id'] = $structure?->id;
            $data['terrain_id'] = $terrain?->id;
            $data['complex_id'] = $terrain?->complex_id;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // The installed product can only change via the "Replace luminaire" action
        // (ViewLuminaire), which correctly creates a new Luminaire row, retires this
        // one and records maintenance atomically (CLA-265). The Edit form UI locks
        // this field (see luminaire-type-gallery-selector.blade.php), but guard here
        // too against a direct/tampered submission bypassing the UI.
        if (array_key_exists('luminaire_type_id', $data)
            && (int) $data['luminaire_type_id'] !== (int) $this->record->luminaire_type_id) {
            $data['luminaire_type_id'] = $this->record->luminaire_type_id;
        }

        if (array_key_exists('luminaire_type_id', $data)) {
            $data['luminaire_subgroup_id'] = $data['luminaire_type_id']
                ? LuminaireType::find($data['luminaire_type_id'])?->luminaire_subgroup_id
                : null;
        }

        if (array_key_exists('serial_number', $data)) {
            $data['serial_number'] = LuminaireResource::resolveSerialNumber($data['serial_number']);
        }

        $touchesPosition = array_key_exists('frame_x', $data) || array_key_exists('frame_y', $data);

        if ($touchesPosition) {
            $currentVersion = (int) ($this->record->position_version ?? 1);
            $expectedVersion = (int) ($data['position_version'] ?? $currentVersion);

            if ($currentVersion <= 0) {
                $currentVersion = 1;
            }

            if ($expectedVersion !== $currentVersion) {
                throw ValidationException::withMessages([
                    'position_version' => __('fieldops::resource.luminaires.position_conflict'),
                ]);
            }

            $data['position_version'] = $currentVersion + 1;
            $data['position_source'] = 'backoffice';
            $data['position_verified_at'] = null;
            $data['position_verified_by_user_id'] = null;
        }

        return $data;
    }
}
