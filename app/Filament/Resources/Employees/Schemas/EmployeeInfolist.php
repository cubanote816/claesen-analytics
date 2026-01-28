<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Models\Employee;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Watchdog Alert (Full Width High Priority)
                Section::make('WATCHDOG ALERT')
                    ->description('Kritieke meldingen voor dit profiel.')
                    ->schema([
                        TextEntry::make('warning_demo')
                            ->label(false)
                            ->default('Kritieke prestatie-afwijking gedetecteerd. Controleer de projectdetails onmiddellijk.')
                            ->weight('bold')
                            ->color('danger')
                            ->icon('heroicon-m-exclamation-triangle')
                            ->iconColor('danger'),
                    ])
                    ->extraAttributes([
                        'style' => 'background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.5); border-radius: 1.5rem;',
                    ])
                    ->columnSpanFull(),
                // Profile Header "One Pillar" Banner
                Section::make()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'md' => 3, 'lg' => 4, 'xl' => 6])
                            ->schema([
                                ImageEntry::make('avatar_url')
                                    ->label(false)
                                    ->circular()
                                    ->imageSize(80)
                                    ->extraAttributes(['class' => 'ring-4 ring-primary-500/10 shadow-lg']),

                                Grid::make(1)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label(false)
                                            ->weight('bold')
                                            ->size('lg'),
                                        TextEntry::make('function')
                                            ->label(false)
                                            ->badge()
                                            ->color('gray')
                                            ->placeholder(__('employees/resource.placeholders.no_function')),
                                    ])
                                    ->columnSpan(1),

                                TextEntry::make('mobile')
                                    ->label(__('employees/resource.fields.mobile'))
                                    ->icon('heroicon-m-phone')
                                    ->iconColor('primary'),

                                TextEntry::make('email')
                                    ->label(__('employees/resource.fields.email'))
                                    ->icon('heroicon-m-envelope')
                                    ->iconColor('primary'),

                                TextEntry::make('full_address')
                                    ->label(__('employees/resource.fields.address'))
                                    ->icon('heroicon-m-map-pin')
                                    ->iconColor('primary')
                                    ->columnSpan(['lg' => 2, 'xl' => 1]),

                                TextEntry::make('fl_active')
                                    ->label(__('employees/resource.fields.status'))
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger')
                                    ->formatStateUsing(fn(bool $state): string => $state ? __('employees/resource.status.active') : __('employees/resource.status.inactive')),
                            ])
                            ->extraAttributes(['class' => 'items-center']),
                    ])
                    ->extraAttributes(['class' => 'bg-white/50 backdrop-blur-sm shadow-sm ring-1 ring-gray-950/5 rounded-3xl p-6'])
                    ->columnSpanFull(),
            ]);
    }
}
