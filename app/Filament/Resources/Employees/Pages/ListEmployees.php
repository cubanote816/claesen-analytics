<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('sync')
                ->label(__('employees/resource.actions.sync.label'))
                ->icon('heroicon-m-arrow-path')
                ->color('info')
                ->action(function (\App\Services\Cafca\EmployeeSyncService $syncService) {
                    try {
                        $stats = $syncService->sync();

                        \Filament\Notifications\Notification::make()
                            ->title(__('employees/resource.actions.sync.notification.success', [
                                'created' => $stats['created'],
                                'updated' => $stats['updated'],
                            ]))
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title(__('employees/resource.actions.sync.notification.error'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
