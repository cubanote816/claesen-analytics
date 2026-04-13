<?php

namespace Modules\Cafca\Filament\Resources\Employees\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Modules\Performance\Services\EmployeePerformanceService;

class EmployeeAnalyticsInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 1. TOP SIGNATURE HUD (Glass Hub)
                Section::make(app()->getLocale() === 'nl' ? 'Core Analytics' : 'Core Analytics')
                    ->components([
                        Grid::make([
                            'default' => 2,
                            'md' => 3,
                            'xl' => 6,
                        ])
                        ->components([
                            ViewEntry::make('team_position')
                                ->label(fn ($record) => app(EmployeePerformanceService::class)->getTeamPosition($record)['label'] ?? 'Team Position')
                                ->view('filament.components.premium-stat-card')
                                ->viewData(['icon' => 'heroicon-m-trophy', 'color' => 'orange', 'compact' => true])
                                ->state(fn ($record) => app(EmployeePerformanceService::class)->getTeamPosition($record)['position'] . '/' . app(EmployeePerformanceService::class)->getTeamPosition($record)['total']),

                            ViewEntry::make('percentile')
                                ->label('Percentile')
                                ->view('filament.components.premium-stat-card')
                                ->viewData(['icon' => 'heroicon-m-chart-bar-square', 'color' => 'success', 'compact' => true])
                                ->state(fn ($record) => app(EmployeePerformanceService::class)->getPercentile($record) . '%'),

                            ViewEntry::make('burnout_risk')
                                ->label('Burnout Risk')
                                ->view('filament.components.premium-stat-card')
                                ->viewData(['icon' => 'heroicon-m-fire', 'color' => 'danger', 'compact' => true])
                                ->state(fn ($record) => app(EmployeePerformanceService::class)->getBurnoutRisk($record) . '%'),

                            ViewEntry::make('total_hours_30d')
                                ->label('Hours (30d)')
                                ->view('filament.components.premium-stat-card')
                                ->viewData(['icon' => 'heroicon-m-clock', 'color' => 'primary', 'compact' => true])
                                ->state(fn ($record) => number_format(app(EmployeePerformanceService::class)->getRecentStats($record)['hours'] ?? 0, 1) . 'h'),

                            ViewEntry::make('performance_30d')
                                ->label('Efficiency (30d)')
                                ->view('filament.components.premium-stat-card')
                                ->viewData(['icon' => 'heroicon-m-check-badge', 'color' => 'success', 'compact' => true])
                                ->state(fn ($record) => round(app(EmployeePerformanceService::class)->getRecentStats($record)['achievement_rate'] ?? 0) . '%'),

                            ViewEntry::make('projects_30d')
                                ->label('Projects (30d)')
                                ->view('filament.components.premium-stat-card')
                                ->viewData(['icon' => 'heroicon-m-briefcase', 'color' => 'primary', 'compact' => true])
                                ->state(fn ($record) => count(app(EmployeePerformanceService::class)->getRecentStats($record)['projects'] ?? [])),
                        ])
                        ->extraAttributes(['class' => 'gap-6 lg:gap-8']),
                    ])
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'bg-transparent border-none shadow-none p-0']),

                // 2. TIMELINE STAGE (Signature HUD Integration)
                Section::make()
                    ->components([
                        \Filament\Schemas\Components\Livewire::make(\App\Livewire\EmployeeProjectTimeline::class, fn(\Modules\Cafca\Models\Employee $record) => ['record' => $record])
                            ->key(fn(\Modules\Cafca\Models\Employee $record) => 'project-timeline-' . $record->id)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'bg-transparent border-none shadow-none p-0 mt-12']),

            ]);
    }
}
