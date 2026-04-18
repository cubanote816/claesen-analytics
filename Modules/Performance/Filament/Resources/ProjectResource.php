<?php

namespace Modules\Performance\Filament\Resources;

use Modules\Cafca\Models\Project;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Performance\Filament\Resources\ProjectResource\Pages;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-bar';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.workforce_performance') ?? 'Workforce & Performance';
    }

    public static function getNavigationLabel(): string
    {
        return app()->getLocale() === 'nl' ? 'Projecten' : 'Projects';
    }

    public static function getModelLabel(): string
    {
        return app()->getLocale() === 'nl' ? 'Project' : 'Project';
    }

    public static function getPluralModelLabel(): string
    {
        return app()->getLocale() === 'nl' ? 'Projecten' : 'Projects';
    }

    public static function form(Schema $schema): Schema
    {
        // Projects are read-only from legacy DB, but we keep the schema for viewing if needed
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => trim($state))
                    ->fontFamily('mono'),

                TextColumn::make('name')
                    ->label(app()->getLocale() === 'nl' ? 'Project Naam' : 'Project Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('manager.name')
                    ->label(app()->getLocale() === 'nl' ? 'Projectleider' : 'Project Manager')
                    ->toggleable(),

                TextColumn::make('total_worked_hours')
                    ->label(app()->getLocale() === 'nl' ? 'Gewerkt (Totaal)' : 'Worked (Total)')
                    ->numeric(1)
                    ->suffix('h')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('pending_debt_amount')
                    ->label(app()->getLocale() === 'nl' ? 'Openstaand' : 'Pending Balance')
                    ->money('EUR')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->alignEnd(),

                IconColumn::make('fl_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-archive-box')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                Filter::make('active')
                    ->label(app()->getLocale() === 'nl' ? 'Alleen Actief' : 'Only Active')
                    ->query(fn (Builder $query) => $query->where('fl_active', true))
                    ->default(),

                Filter::make('worked_this_month')
                    ->label(app()->getLocale() === 'nl' ? 'Gewerkt deze maand' : 'Worked this month')
                    ->query(fn (Builder $query) => $query->whereHas('labor', fn ($q) => 
                        $q->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
                    )),

                Filter::make('pending_collection')
                   ->label(app()->getLocale() === 'nl' ? 'Facturatie Wachtend' : 'Pending Collections')
                   ->query(fn (Builder $query) => $query->whereHas('invoices', function ($q) {
                        return $q->select(DB::raw('project_id'))
                            ->groupBy('project_id')
                            ->havingRaw('SUM(total_price) - SUM(total_paid) > 0.05');
                    })),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->persistFiltersInSession()
            ->actions([
                \Filament\Actions\ViewAction::make()
                    ->url(fn ($record) => $record->insight 
                        ? ProjectInsightResource::getUrl('view', ['record' => $record->insight->id]) 
                        : null)
                    ->icon('heroicon-m-sparkles')
                    ->label(app()->getLocale() === 'nl' ? 'AI Analyse' : 'AI Analysis')
                    ->color('primary'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
        ];
    }
}
