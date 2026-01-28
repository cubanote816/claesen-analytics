<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): \Illuminate\Contracts\Support\Htmlable|string
    {
        return $this->record->name;
    }

    public static function getNavigationLabel(): string
    {
        return __('employees/resource.navigation.details');
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
