<?php

namespace Modules\Cafca\Filament\Resources\Employees\Schemas;

use Modules\Cafca\Models\Employee;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;

class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Profile Header "One Pillar" Banner
                Section::make()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'md' => 3, 'lg' => 4, 'xl' => 6])
                            ->schema([
                                ImageEntry::make('avatar_url')
                                    ->label(__('employees/resource.fields.avatar'))
                                    ->circular()
                                    ->imageSize(80)
                                    ->extraAttributes(['class' => 'ring-4 ring-primary-500/10 shadow-lg']),

                                Grid::make(1)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label(__('employees/resource.fields.name'))
                                            ->weight('bold')
                                            ->size('lg'),
                                        TextEntry::make('function')
                                            ->label(__('employees/resource.fields.job_function'))
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
                                    ->label(__('employees/resource.fields.is_active'))
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger')
                                    ->formatStateUsing(fn(bool $state): string => $state ? __('employees/resource.status.active') : __('employees/resource.status.inactive')),
                            ])
                            ->extraAttributes(['class' => 'items-center']),
                    ])
                    ->extraAttributes(['class' => 'bg-white/50 backdrop-blur-sm shadow-sm ring-1 ring-gray-950/5 rounded-3xl p-6'])
                    ->columnSpanFull(),

                // Watchdog Alert (Premium Refined)
                Section::make(__('employees/resource.sections.watchdog_alerts'))
                    ->description(__('employees/resource.sections.watchdog_description'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('warning_demo')
                                    ->label(false)
                                    ->default(__('employees/resource.messages.watchdog_warning'))
                                    ->weight('black')
                                    ->color('danger')
                                    ->icon('heroicon-m-exclamation-triangle')
                                    ->size('lg'),
                                
                                TextEntry::make('action_required')
                                    ->label(false)
                                    ->default(app()->getLocale() === 'nl' ? 'INTERVENTIE VEREIST' : 'INTERVENTION REQUIRED')
                                    ->badge()
                                    ->color('danger')
                                    ->alignEnd(),
                            ])
                    ])
                    ->extraAttributes([
                        'class' => 'overflow-hidden !border-0',
                        'style' => 'background: linear-gradient(90deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.02) 100%); border-left: 6px solid #ef4444 !important; border-radius: 1.25rem; backdrop-filter: blur(8px); box-shadow: 0 4px 6px -1px rgb(239 68 68 / 0.1);',
                    ])
                    ->columnSpanFull(),

                // Talent Snapshot (Ultra-Compact)
                Section::make()
                    ->components([
                        Grid::make(['default' => 1, 'md' => 4])
                            ->components([
                                TextEntry::make('insight.archetype_label')
                                    ->label(app()->getLocale() === 'nl' ? 'Talent Profiel' : 'Talent Profile')
                                    ->weight('bold')
                                    ->color('primary')
                                    ->prefix(fn($record) => $record->insight?->archetype_icon . ' '),
                                
                                TextEntry::make('insight.burnout_risk_score')
                                    ->label(app()->getLocale() === 'nl' ? 'Burnout Risico' : 'Burnout Risk')
                                    ->suffix('%')
                                    ->weight('bold')
                                    ->color(fn($state) => (int)$state > 70 ? 'danger' : ((int)$state > 40 ? 'warning' : 'success')),
                                
                                TextEntry::make('active_projects_summary')
                                    ->label(app()->getLocale() === 'nl' ? 'Huidige Opdracht' : 'Current Assignment')
                                    ->state(function (Employee $record) {
                                        return \Modules\Cafca\Models\Labor::where('employee_id', $record->id)
                                            ->where('date', '>=', now()->subDays(15))
                                            ->with('project')
                                            ->get()
                                            ->pluck('project.name')
                                            ->unique()
                                            ->implode(', ') ?: 'Standby';
                                    })
                                    ->icon('heroicon-m-briefcase')
                                    ->size('xs')
                                    ->limit(30),

                                \Filament\Infolists\Components\ViewEntry::make('performance_trend')
                                    ->label(app()->getLocale() === 'nl' ? 'Uren Trend (6m)' : 'Activity Trend (6m)')
                                    ->view('filament.components.sparkline-trend')
                                    ->state(fn($record) => app(\Modules\Performance\Services\EmployeePerformanceService::class)->getShortTrend($record)),
                            ])
                    ])
                    ->extraAttributes(['class' => 'bg-primary-50/10 border-primary-100/50 rounded-2xl p-2'])
                    ->columnSpanFull(),
            ]);
    }
}
