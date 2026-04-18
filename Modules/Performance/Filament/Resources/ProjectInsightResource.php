<?php

namespace Modules\Performance\Filament\Resources;

use Modules\Performance\Filament\Resources\ProjectInsightResource\Pages;
use Modules\Performance\Models\ProjectInsight;
use Modules\Performance\Models\Mirror\MirrorProject;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Infolists\Components\TextEntry;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Entry;

class ProjectInsightResource extends Resource
{
    protected static ?string $model = ProjectInsight::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationBadge(): ?string
    {
        return 'DEMO';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Analyse & Intelligentie';
    }

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('performance::project_insight.plural_model_label');
    }

    public static function getModelLabel(): string
    {
        return __('performance::project_insight.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('performance::project_insight.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('performance::project_insight.sections.analysis_results'))
                    ->schema([
                        TextInput::make('efficiency_score')
                            ->label(__('performance::project_insight.fields.efficiency_score'))
                            ->numeric(),
                        Textarea::make('ai_summary')
                            ->label(__('performance::project_insight.fields.gemini_summary')),
                        TextInput::make('last_data_hash')
                            ->disabled(),
                    ])
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('performance::project_insight.sections.project_dna'))
                    ->icon('heroicon-m-finger-print')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('project.name')
                            ->label(__('performance::project_insight.fields.project_name'))
                            ->weight('bold')
                            ->color('primary'),
                        TextEntry::make('project.manager.name')
                            ->label(__('performance::project_insight.fields.project_manager'))
                            ->placeholder('N/A'),
                        TextEntry::make('project.project_type_name')
                            ->label(__('performance::project_insight.fields.project_type'))
                            ->badge(),
                    ]),

                Section::make(__('performance::project_insight.sections.analysis_results'))
                    ->icon('heroicon-m-sparkles')
                    ->schema([
                        TextEntry::make('ai_summary')
                            ->label(__('performance::project_insight.fields.gemini_summary'))
                            ->prose(),
                        TextEntry::make('golden_rule')
                            ->label(__('performance::project_insight.fields.golden_lesson'))
                            ->weight('black')
                            ->color('warning')
                            ->size('lg'),
                    ]),

                Section::make(__('performance::project_insight.sections.swot_matrix'))
                    ->columns(2)
                    ->schema([
                        Group::make([
                            TextEntry::make('full_dna.swot.strengths')
                                ->label(__('performance::project_insight.fields.strengths'))
                                ->listWithLineBreaks()
                                ->bulleted()
                                ->color('success'),
                        ]),
                        Group::make([
                            TextEntry::make('full_dna.swot.weaknesses')
                                ->label(__('performance::project_insight.fields.weaknesses'))
                                ->listWithLineBreaks()
                                ->bulleted()
                                ->color('danger'),
                        ]),
                        Group::make([
                            TextEntry::make('full_dna.swot.opportunities')
                                ->label(__('performance::project_insight.fields.opportunities'))
                                ->listWithLineBreaks()
                                ->bulleted()
                                ->color('info'),
                        ]),
                        Group::make([
                            TextEntry::make('full_dna.swot.threats')
                                ->label(__('performance::project_insight.fields.threats'))
                                ->listWithLineBreaks()
                                ->bulleted()
                                ->color('warning'),
                        ]),
                    ]),

                Section::make(__('performance::project_insight.fields.time_performance'))
                    ->icon('heroicon-m-clock')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('project.planned_hours')
                            ->label(__('performance::project_insight.fields.planned_hours'))
                            ->numeric(2)
                            ->suffix('h')
                            ->color('info'),
                        TextEntry::make('project.total_worked_hours')
                            ->label(fn($record) => $record->project?->fl_active 
                                ? __('performance::project_insight.fields.worked_hours_active') 
                                : __('performance::project_insight.fields.worked_hours_finished'))
                            ->numeric(2)
                            ->suffix('h')
                            ->weight('bold'),
                        TextEntry::make('project.time_efficiency')
                            ->label(__('performance::project_insight.fields.efficiency'))
                            ->numeric(2)
                            ->suffix('%')
                            ->weight('black')
                            ->color(fn($state) => $state > 100 ? 'danger' : 'success')
                            ->icon(fn($state) => $state > 100 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle'),
                    ]),

                Section::make(__('performance::project_insight.fields.project_team'))
                    ->icon('heroicon-m-users')
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('project.labor_summary')
                            ->label(false)
                            ->grid(3)
                            ->contained(false)
                            ->schema([
                                \Filament\Schemas\Components\Grid::make(1)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label(false)
                                            ->weight('bold')
                                            ->color('primary')
                                            ->icon('heroicon-m-user')
                                            ->url(function ($record) {
                                                if (! $record || ! isset($record->employee_id)) {
                                                    return null;
                                                }
                                                return \Modules\Cafca\Filament\Resources\EmployeeResource::getUrl('view', ['record' => $record->employee_id]);
                                            }),
                                        TextEntry::make('hours')
                                            ->label(false)
                                            ->badge()
                                            ->color('success')
                                            ->suffix('h total'),
                                    ])
                            ])
                    ]),

                Section::make(__('performance::project_insight.sections.financial_overview'))
                    ->icon('heroicon-m-currency-euro')
                    ->compact()
                    ->contained(false)
                    ->schema([
                        ViewEntry::make('project.financial_summary')
                            ->label(false)
                            ->view('performance::components.financial-status-list')
                            ->viewData(fn (Entry $entry) => [
                                'metrics' => [
                                    'total_invoiced_amount' => [
                                        'label' => __('performance::project_insight.fields.total_invoiced'),
                                        'value' => $entry->getRecord()->project?->total_invoiced_amount ?? 0,
                                        'formatted' => \Illuminate\Support\Number::currency($entry->getRecord()->project?->total_invoiced_amount ?? 0, 'EUR'),
                                        'icon' => 'heroicon-m-document-text',
                                        'color' => 'info',
                                    ],
                                    'total_paid_amount' => [
                                        'label' => __('performance::project_insight.fields.total_paid'),
                                        'value' => $entry->getRecord()->project?->total_paid_amount ?? 0,
                                        'formatted' => \Illuminate\Support\Number::currency($entry->getRecord()->project?->total_paid_amount ?? 0, 'EUR'),
                                        'icon' => 'heroicon-m-check-badge',
                                        'color' => 'success',
                                    ],
                                    'pending_debt_amount' => [
                                        'label' => __('performance::project_insight.fields.pending_balance'),
                                        'value' => $entry->getRecord()->project?->pending_debt_amount ?? 0,
                                        'formatted' => \Illuminate\Support\Number::currency($entry->getRecord()->project?->pending_debt_amount ?? 0, 'EUR'),
                                        'icon' => 'heroicon-m-scale',
                                        'color' => 'danger',
                                    ],
                                    'to_be_invoiced_amount' => [
                                        'label' => __('performance::project_insight.fields.to_be_invoiced'),
                                        'value' => $entry->getRecord()->project?->to_be_invoiced_amount ?? 0,
                                        'formatted' => \Illuminate\Support\Number::currency($entry->getRecord()->project?->to_be_invoiced_amount ?? 0, 'EUR'),
                                        'icon' => 'heroicon-m-receipt-percent',
                                        'color' => 'warning',
                                    ],
                                    'pending_invoices_count' => [
                                        'label' => __('performance::project_insight.fields.pending_invoices_count'),
                                        'value' => $count = ($entry->getRecord()->project?->pending_invoices_count ?? 0),
                                        'formatted' => $count,
                                        'icon' => $count > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle',
                                        'color' => $count > 0 ? 'danger' : 'info',
                                        'action' => $entry->getAction('view_pending_details'),
                                    ],
                                ],
                            ])
                            ->registerActions([
                                Action::make('view_pending_details')
                                    ->label('')
                                    ->tooltip(__('performance::project_insight.fields.modal_title'))
                                    ->icon('heroicon-m-eye')
                                    ->color('danger')
                                    ->size('xs')
                                    ->link()
                                    ->modalHeading(__('performance::project_insight.fields.modal_title'))
                                    ->modalWidth('xl')
                                    ->modalSubmitAction(false)
                                    ->modalCancelActionLabel('Sluiten')
                                    ->schema([
                                        RepeatableEntry::make('project.pending_invoices')
                                            ->label('')
                                            ->schema([
                                                Grid::make(5)
                                                    ->schema([
                                                        TextEntry::make('id')
                                                            ->label(__('performance::project_insight.fields.invoice_id'))
                                                            ->weight('bold'),
                                                        TextEntry::make('print_date')
                                                            ->label(__('performance::project_insight.fields.invoice_date'))
                                                            ->date(),
                                                        TextEntry::make('total_price')
                                                            ->label(__('performance::project_insight.fields.invoice_amount'))
                                                            ->money('EUR')
                                                            ->alignEnd(),
                                                        TextEntry::make('total_paid')
                                                            ->label(__('performance::project_insight.fields.invoice_paid'))
                                                            ->money('EUR')
                                                            ->color('success')
                                                            ->alignEnd(),
                                                        TextEntry::make('balance')
                                                            ->label(__('performance::project_insight.fields.pending_balance'))
                                                            ->money('EUR')
                                                            ->color('danger')
                                                            ->weight('black'),
                                                    ])
                                            ])
                                    ])
                            ]),
                    ]),

                Section::make(__('performance::project_insight.sections.metadata'))
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('efficiency_score')
                            ->label(__('performance::project_insight.fields.efficiency_score'))
                            ->suffix('%'),
                        TextEntry::make('last_audited_at')
                            ->label(__('performance::project_insight.fields.last_analyzed'))
                            ->dateTime(),
                        TextEntry::make('project_id')
                            ->label(__('performance::project_insight.fields.project_id')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project_id')
                    ->label(__('performance::project_insight.fields.project_id'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('efficiency_score')
                    ->label(__('performance::project_insight.fields.score'))
                    ->sortable()
                    ->formatStateUsing(fn($state) => number_format($state, 2) . '%')
                    ->color(fn($state) => $state > 90 ? 'success' : ($state > 70 ? 'warning' : 'danger')),

                TextColumn::make('last_audited_at')
                    ->label(__('performance::project_insight.fields.last_analyzed'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->poll('10s')
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make(),
                Action::make('reanalyze')
                    ->label(__('performance::project_insight.fields.reanalyze'))
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn($record) => \Modules\Performance\Jobs\AuditProjectJob::dispatch($record->project_id))
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->project && MirrorProject::find($record->project_id)?->fl_active === false),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectInsights::route('/'),
            'view' => Pages\ViewProjectInsight::route('/{record}'),
        ];
    }
}
