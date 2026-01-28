<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn(Employee $record): string => EmployeeResource::getUrl('view', ['record' => $record]))
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->imageSize(40),

                TextColumn::make('name')
                    ->label(__('employees/resource.fields.name'))
                    ->description(fn(Employee $record): string => $record->job_function)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('full_address')
                    ->label(__('employees/resource.fields.address'))
                    ->icon('heroicon-m-map-pin')
                    ->iconColor('gray')
                    ->wrap()
                    ->size('xs')
                    ->toggleable(),

                TextColumn::make('mobile')
                    ->label(__('employees/resource.fields.mobile'))
                    ->icon('heroicon-m-phone')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('email')
                    ->label(__('employees/resource.fields.email'))
                    ->icon('heroicon-m-envelope')
                    ->copyable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('fl_active')
                    ->label(__('employees/resource.fields.is_active'))
                    ->badge()
                    ->color(fn(bool $state): string => match ($state) {
                        true => 'success',
                        false => 'danger',
                    })
                    ->formatStateUsing(fn(bool $state): string => $state ? __('employees/resource.status.active') : __('employees/resource.status.inactive')),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('fl_active')
                    ->label(__('employees/resource.fields.is_active'))
                    ->placeholder(__('employees/resource.status.all'))
                    ->options([
                        '1' => __('employees/resource.status.active'),
                        '0' => __('employees/resource.status.inactive'),
                    ])
                    ->native(false),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                // No bulk delete as per instructions
            ]);
    }
}
