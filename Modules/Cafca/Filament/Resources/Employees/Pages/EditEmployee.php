<?php

namespace Modules\Cafca\Filament\Resources\Employees\Pages;

use Modules\Cafca\Filament\Resources\EmployeeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    public static function getNavigationLabel(): string
    {
        return __('employees/resource.navigation.edit');
    }

    public function getSubheading(): \Illuminate\Contracts\Support\Htmlable|string|null
    {
        $isNl = app()->getLocale() === 'nl';
        return $isNl ? 'Pas contactgegevens en profielfoto aan.' : 'Update contact details and profile photo.';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->color('gray')
                ->outlined()
                ->icon('heroicon-o-eye'),

            DeleteAction::make()
                ->color('danger')
                ->outlined()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function getFormActions(): array
    {
        $isNl = app()->getLocale() === 'nl';

        return [
            $this->getSaveFormAction()
                ->label($isNl ? 'Wijzigingen opslaan' : 'Save changes'),

            $this->getCancelFormAction()
                ->label($isNl ? 'Annuleren' : 'Cancel')
                ->color('gray'),
        ];
    }
}
