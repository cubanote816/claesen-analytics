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

    public function getHeading(): \Illuminate\Contracts\Support\Htmlable|string
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
            \Filament\Actions\Action::make('analyze')
                ->label(__('employees/resource.actions.analyze.label'))
                ->icon('heroicon-o-cpu-chip')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('employees/resource.actions.analyze.confirm.title'))
                ->modalDescription(__('employees/resource.actions.analyze.confirm.body'))
                ->action(function () {
                    \App\Jobs\AnalyzeEmployeeJob::dispatchSync($this->record->id);
                    \Filament\Notifications\Notification::make()
                        ->title(__('employees/resource.actions.analyze.notification.success'))
                        ->success()
                        ->send();
                    $this->refresh();
                })
                ->visible(fn() => auth()->user()->hasRole(['super_admin', 'admin'])),

            EditAction::make()
                ->label(__('employees/resource.actions.edit.label') ?? __('employees/resource.navigation.edit'))
                ->color('primary')
                ->outlined(),
        ];
    }
}
