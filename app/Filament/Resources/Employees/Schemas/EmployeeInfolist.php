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

                // Watchdog Alert (Full Width High Priority)
                Section::make(__('employees/resource.sections.watchdog_alerts'))
                    ->description(__('employees/resource.sections.watchdog_description'))
                    ->schema([
                        TextEntry::make('warning_demo')
                            ->label(false)
                            ->default(__('employees/resource.messages.watchdog_warning'))
                            ->weight('bold')
                            ->color('danger')
                            ->icon('heroicon-m-exclamation-triangle')
                            ->iconColor('danger'),
                    ])
                    ->extraAttributes([
                        'style' => 'background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.5); border-radius: 1.5rem;',
                    ])
                    ->columnSpanFull(),

                // Performance Dashboard Section
                Section::make(__('employees/resource.sections.performance_dashboard'))
                    ->schema([
                        // KPI Grid
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('total_hours')
                                    ->label(__('employees/resource.stats.total_hours'))
                                    ->size('xl')
                                    ->weight('bold')
                                    ->placeholder(__('employees/resource.placeholders.total_hours')),

                                TextEntry::make('projects_count')
                                    ->label(__('employees/resource.stats.projects_count'))
                                    ->size('xl')
                                    ->weight('bold')
                                    ->placeholder(__('employees/resource.placeholders.projects_count')),

                                TextEntry::make('efficiency')
                                    ->label(__('employees/resource.stats.efficiency'))
                                    ->size('xl')
                                    ->weight('bold')
                                    ->placeholder(__('employees/resource.stats.efficiency')),
                            ]),

                        // AI Analysis Section
                        Grid::make(3)
                            ->schema([
                                Section::make(__('employees/resource.sections.ai_insights'))
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('insight.archetype_label')
                                                    ->label(__('employees/resource.insights.archetype'))
                                                    ->weight('bold')
                                                    ->color('primary')
                                                    ->size('lg')
                                                    ->prefix(fn($record) => $record->insight?->archetype_icon . ' ')
                                                    ->columnSpanFull(),

                                                TextEntry::make('insight.burnout_risk_score')
                                                    ->label(__('employees/resource.insights.burnout_risk'))
                                                    ->suffix('%')
                                                    ->weight('bold')
                                                    ->color(fn(int $state): string => match (true) {
                                                        $state > 70 => 'danger',
                                                        $state > 40 => 'warning',
                                                        default => 'success',
                                                    }),

                                                TextEntry::make('insight.efficiency_trend')
                                                    ->label(__('employees/resource.insights.efficiency_trend'))
                                                    ->badge()
                                                    ->color(fn(string $state): string => match ($state) {
                                                        'UP' => 'success',
                                                        'DOWN' => 'danger',
                                                        default => 'gray',
                                                    })
                                                    ->formatStateUsing(fn(string $state): string => match ($state) {
                                                        'UP' => '↗ ' . __('employees/resource.insights.status.increasing'),
                                                        'DOWN' => '↘ ' . __('employees/resource.insights.status.decreasing'),
                                                        default => '→ ' . __('employees/resource.insights.status.stable'),
                                                    }),

                                                TextEntry::make('insight.manager_insight')
                                                    ->label(__('employees/resource.insights.manager_insight'))
                                                    ->columnSpanFull(),

                                                TextEntry::make('insight.last_audited_at')
                                                    ->label(__('employees/resource.insights.last_audited'))
                                                    ->since()
                                                    ->size('xs')
                                                    ->color('gray')
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->columnSpan(2)
                                    ->compact(),

                                Section::make(__('employees/resource.sections.project_timeline'))
                                    ->schema([
                                        TextEntry::make('project_timeline')
                                            ->label(false)
                                            ->placeholder(__('employees/resource.placeholders.project_timeline_loading')),
                                    ])
                                    ->columnSpan(1)
                                    ->compact(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
