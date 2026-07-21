<?php

namespace Modules\FieldOps\Filament\Resources\Luminaires\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Models\LuminaireType;

class EditLuminaire extends EditRecord
{
    protected static string $resource = LuminaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            RestoreAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('luminaire_type_id', $data)) {
            $data['luminaire_subgroup_id'] = $data['luminaire_type_id']
                ? LuminaireType::find($data['luminaire_type_id'])?->luminaire_subgroup_id
                : null;
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
