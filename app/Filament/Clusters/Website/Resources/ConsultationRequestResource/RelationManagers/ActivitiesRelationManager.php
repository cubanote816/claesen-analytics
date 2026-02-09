<?php

namespace App\Filament\Clusters\Website\Resources\ConsultationRequestResource\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    #[On('refreshActivities')]
    public function refresh(): void
    {
        // This method just serves as a target for the listener to trigger a re-render
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('website.activities.fields.title')),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('website.activities.fields.type'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'status_change' => 'info',
                        'priority_change' => 'warning',
                        'assignment_change' => 'primary',
                        'follow_up_update' => 'info',
                        'comment' => 'gray',
                        'created' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => __("website.activities.types.{$state}")),
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('website.activities.fields.user'))
                    ->placeholder('Systeem'),
                Tables\Columns\TextColumn::make('activity_at')
                    ->label(__('website.activities.fields.date'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                ViewAction::make()
                    ->modalHeading(__('website.activities.label'))
                    ->modalWidth('3xl'),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('activity_at', 'desc')
            ->recordAction(ViewAction::class);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Metadata Header (Horizontal spread across the full width)
                Grid::make(4)
                    ->schema([
                        TextEntry::make('type')
                            ->label(__('website.activities.fields.type'))
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'status_change' => 'info',
                                'priority_change' => 'warning',
                                'assignment_change' => 'primary',
                                'follow_up_update' => 'info',
                                'comment' => 'gray',
                                'created' => 'success',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn(string $state): string => __("website.activities.types.{$state}")),

                        TextEntry::make('user.name')
                            ->label(__('website.activities.fields.user'))
                            ->icon(\Filament\Support\Icons\Heroicon::OutlinedUser)
                            ->placeholder('Systeem'),

                        TextEntry::make('activity_at')
                            ->label(__('website.activities.fields.date'))
                            ->icon(\Filament\Support\Icons\Heroicon::OutlinedCalendar)
                            ->dateTime(),

                        TextEntry::make('id')
                            ->label('ID')
                            ->fontFamily('mono')
                            ->copyable()
                            ->color('gray')
                            ->size('xs'),
                    ])
                    ->extraAttributes([
                        'class' => 'bg-gray-50/50 dark:bg-white/5 p-4 rounded-xl border border-gray-100 dark:border-white/10 mb-6',
                    ]),

                // Content Area (Uses Full Width)
                Grid::make(1)
                    ->schema([
                        TextEntry::make('title')
                            ->hiddenLabel()
                            ->weight('bold')
                            ->size('xl')
                            ->extraAttributes(['class' => 'text-primary-600 dark:text-primary-400 mb-2']),

                        TextEntry::make('description')
                            ->label(__('website.activities.fields.description'))
                            ->prose()
                            ->visible(fn($record) => !empty($record->description)),

                        Fieldset::make('Veranderingen')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('data.old_value')
                                            ->label(__('website.activities.fields.old_value'))
                                            ->badge()
                                            ->color('gray'),
                                        TextEntry::make('data.new_value')
                                            ->label(__('website.activities.fields.new_value'))
                                            ->badge()
                                            ->color('success'),
                                    ]),
                            ])
                            ->visible(fn($record) => isset($record->data['old_value']) || isset($record->data['new_value'])),
                    ])
            ]);
    }
}
