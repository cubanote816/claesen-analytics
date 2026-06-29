<?php

namespace Modules\Cafca\Filament\Resources\Employees\Pages;

use Modules\Cafca\Filament\Resources\EmployeeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): \Illuminate\Contracts\Support\Htmlable|string
    {
        return $this->record->name;
    }

    public function getHeading(): \Illuminate\Contracts\Support\Htmlable|string
    {
        return $this->record->name;
    }

    public function getSubheading(): \Illuminate\Contracts\Support\Htmlable|string|null
    {
        $isNl   = app()->getLocale() === 'nl';
        $status = $this->record->fl_active
            ? ($isNl ? 'Actief' : 'Active')
            : ($isNl ? 'Inactief' : 'Inactive');

        $function = $this->record->function ?: ($isNl ? 'Geen functie opgegeven' : 'No function specified');

        return $function . ' · ' . $status;
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
            \Filament\Actions\Action::make('view_hours')
                ->label(app()->getLocale() === 'nl' ? 'Uren bekijken' : 'View Hours')
                ->icon('heroicon-o-clock')
                ->color('info')
                ->outlined()
                ->url(fn() => \Modules\Employee\Filament\Pages\EmployeeMonthStats::getUrl([
                    'employee_id' => (string) $this->record->id,
                    'month'       => now()->format('Y-m'),
                ])),

            \Filament\Actions\Action::make('analyze')
                ->label(__('employees/resource.actions.analyze.label'))
                ->icon('heroicon-o-cpu-chip')
                ->color('warning')
                ->outlined()
                ->requiresConfirmation()
                ->modalHeading(__('employees/resource.actions.analyze.confirm.title'))
                ->modalDescription(__('employees/resource.actions.analyze.confirm.body'))
                ->action(function () {
                    \Modules\Performance\Jobs\AnalyzeEmployeeJob::dispatchSync($this->record->id);
                    \Filament\Notifications\Notification::make()
                        ->title(__('employees/resource.actions.analyze.notification.success'))
                        ->success()
                        ->send();
                    $this->refresh();
                })
                ->visible(fn() => auth()->user()->hasRole(['super_admin', 'admin'])),

            EditAction::make()
                ->label(__('employees/resource.actions.edit.label') ?? __('employees/resource.navigation.edit'))
                ->color('primary'),
        ];
    }
}
