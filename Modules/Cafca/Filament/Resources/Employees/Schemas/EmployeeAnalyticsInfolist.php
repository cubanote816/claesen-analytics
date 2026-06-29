<?php

namespace Modules\Cafca\Filament\Resources\Employees\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Performance\Services\EmployeePerformanceService;

class EmployeeAnalyticsInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $isNl = app()->getLocale() === 'nl';

        return $schema
            ->components([
                // ── KPI strip ────────────────────────────────────────────────
                Section::make()
                    ->schema([
                        Grid::make(['default' => 2, 'md' => 3, 'xl' => 6])
                            ->schema([
                                ViewEntry::make('team_position')
                                    ->label(fn($record) => app(EmployeePerformanceService::class)->getTeamPosition($record)['label'] ?? ($isNl ? 'Team Positie' : 'Team Position'))
                                    ->view('filament.components.premium-stat-card')
                                    ->viewData(['icon' => 'heroicon-m-trophy', 'color' => 'orange', 'compact' => true])
                                    ->state(fn($record) => app(EmployeePerformanceService::class)->getTeamPosition($record)['position']
                                        . '/' . app(EmployeePerformanceService::class)->getTeamPosition($record)['total']),

                                ViewEntry::make('percentile')
                                    ->label($isNl ? 'Percentiel' : 'Percentile')
                                    ->view('filament.components.premium-stat-card')
                                    ->viewData(['icon' => 'heroicon-m-chart-bar-square', 'color' => 'success', 'compact' => true])
                                    ->state(fn($record) => app(EmployeePerformanceService::class)->getPercentile($record) . '%'),

                                ViewEntry::make('burnout_risk')
                                    ->label($isNl ? 'Burnout Risico' : 'Burnout Risk')
                                    ->view('filament.components.premium-stat-card')
                                    ->viewData(['icon' => 'heroicon-m-fire', 'color' => 'danger', 'compact' => true])
                                    ->state(fn($record) => app(EmployeePerformanceService::class)->getBurnoutRisk($record) . '%'),

                                ViewEntry::make('total_hours_30d')
                                    ->label($isNl ? 'Uren (30d)' : 'Hours (30d)')
                                    ->view('filament.components.premium-stat-card')
                                    ->viewData(['icon' => 'heroicon-m-clock', 'color' => 'primary', 'compact' => true])
                                    ->state(fn($record) => number_format(
                                        app(EmployeePerformanceService::class)->getRecentStats($record)['hours'] ?? 0, 1
                                    ) . 'h'),

                                ViewEntry::make('performance_30d')
                                    ->label($isNl ? 'Efficiëntie (30d)' : 'Efficiency (30d)')
                                    ->view('filament.components.premium-stat-card')
                                    ->viewData(['icon' => 'heroicon-m-check-badge', 'color' => 'success', 'compact' => true])
                                    ->state(fn($record) => round(
                                        app(EmployeePerformanceService::class)->getRecentStats($record)['achievement_rate'] ?? 0
                                    ) . '%'),

                                ViewEntry::make('projects_30d')
                                    ->label($isNl ? 'Projecten (30d)' : 'Projects (30d)')
                                    ->view('filament.components.premium-stat-card')
                                    ->viewData(['icon' => 'heroicon-m-briefcase', 'color' => 'primary', 'compact' => true])
                                    ->state(fn($record) => count(
                                        app(EmployeePerformanceService::class)->getRecentStats($record)['projects'] ?? []
                                    )),
                            ])
                            ->extraAttributes(['class' => 'gap-3 lg:gap-4']),
                    ])
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'bg-transparent border-none shadow-none p-0']),

                // ── Project timeline ─────────────────────────────────────────
                Section::make()
                    ->schema([
                        \Filament\Schemas\Components\Livewire::make(
                            \App\Livewire\EmployeeProjectTimeline::class,
                            fn(\Modules\Cafca\Models\Employee $record) => ['record' => $record]
                        )
                            ->key(fn(\Modules\Cafca\Models\Employee $record) => 'project-timeline-' . $record->id)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'bg-transparent border-none shadow-none p-0 mt-6']),

                // ── AI Profile & actionable insights ─────────────────────────
                ViewEntry::make('ai_insights_block')
                    ->label(false)
                    ->view('filament.components.employee-ai-insights')
                    ->state(fn($record) => $record)
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'mt-4']),
            ]);
    }
}
