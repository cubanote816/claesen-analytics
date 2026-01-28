<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    public function getRecordTitle(): string
    {
        return \Illuminate\Support\Str::headline($this->record->name);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public $tempPermissions = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->tempPermissions = collect($data)
            ->filter(fn($value, $key) => str_starts_with($key, 'permissions_'))
            ->flatten()
            ->toArray();

        return collect($data)
            ->reject(fn($value, $key) => str_starts_with($key, 'permissions_'))
            ->toArray();
    }

    protected function afterSave(): void
    {
        $this->record->syncPermissions($this->tempPermissions);
    }
}
