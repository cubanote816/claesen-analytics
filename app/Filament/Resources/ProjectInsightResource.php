<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectInsightResource\Pages;
use App\Models\ProjectInsight;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput; // Inputs namespace
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema; // Unified Schema
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class ProjectInsightResource extends Resource
{
    protected static ?string $model = ProjectInsight::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationLabel = 'AI Insights';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Analysis Results')
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('efficiency_score')
                                    ->label('Efficiency Score')
                                    ->numeric()
                                    ->suffix('%')
                                    ->disabled(),
                                Textarea::make('ai_summary')
                                    ->label('Gemini Summary')
                                    ->rows(5)
                                    ->disabled(),
                            ]),
                    ]),
                Section::make('Metadata')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('last_audited_at')
                                    ->label('Last Analyzed')
                                    ->disabled(),
                                TextInput::make('project_id')
                                    ->label('Project ID')
                                    ->disabled(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project_id')
                    ->label('Project ID')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('efficiency_score')
                    ->label('Score')
                    ->sortable()
                    ->formatStateUsing(fn($state) => number_format($state, 2) . '%')
                    ->color(fn($state) => $state > 90 ? 'success' : ($state > 70 ? 'warning' : 'danger')),

                TextColumn::make('last_audited_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->poll('10s') // Polling for updates as requested
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
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
            // 'create' => Pages\CreateProjectInsight::route('/create'), // Disabled creation
            'view' => Pages\ViewProjectInsight::route('/{record}'),
            // 'edit' => Pages\EditProjectInsight::route('/{record}/edit'), // Disabled editing
        ];
    }
}
