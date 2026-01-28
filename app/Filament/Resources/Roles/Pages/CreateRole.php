<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    public $tempPermissions = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->tempPermissions = collect($data)
            ->filter(fn($value, $key) => str_starts_with($key, 'permissions_'))
            ->flatten()
            ->toArray();

        // Remove permission fields from data
        return collect($data)
            ->reject(fn($value, $key) => str_starts_with($key, 'permissions_'))
            ->toArray();
    }

    protected function afterCreate(): void
    {
        $this->record->syncPermissions($this->tempPermissions);
    }
}
