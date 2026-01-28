<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeePerformance extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    public static function getNavigationLabel(): string
    {
        return __('employees/resource.navigation.performance');
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-presentation-chart-line';
    }
    public function getTitle(): string
    {
        return __('employees/resource.navigation.performance') . ' - ' . $this->getRecordTitle();
    }

    public function getHeading(): string
    {
        return __('employees/resource.navigation.performance');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
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

                // Watchdog Alert (Full Width High Priority)
                Section::make('WATCHDOG ALERT')
                    ->description('Kritieke meldingen voor dit dashboard.')
                    ->schema([
                        TextEntry::make('warning_demo')
                            ->label(false)
                            ->default('AI Analyse waarschuwing: Ongebruikelijke daling in efficiëntie gedetecteerd over de laatste 7 dagen.')
                            ->weight('bold')
                            ->color('danger')
                            ->icon('heroicon-m-bolt')
                            ->iconColor('danger'),
                    ])
                    ->extraAttributes([
                        'style' => 'background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.5); border-radius: 1.5rem;',
                    ])
                    ->columnSpanFull(),

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
                        Grid::make(2)
                            ->schema([
                                Section::make(__('employees/resource.sections.ai_insights'))
                                    ->schema([
                                        TextEntry::make('ai_insights')
                                            ->label(false)
                                            ->markdown()
                                            ->placeholder(__('employees/resource.placeholders.ai_insights_loading')),
                                    ])
                                    ->compact(),

                                Section::make(__('employees/resource.sections.project_timeline'))
                                    ->schema([
                                        TextEntry::make('project_timeline')
                                            ->label(false)
                                            ->placeholder(__('employees/resource.placeholders.project_timeline_loading')),
                                    ])
                                    ->compact(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
