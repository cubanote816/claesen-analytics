<?php

namespace Modules\Prospects\Filament\Resources;

use Modules\Prospects\Models\SyncHistory;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\IconEntry;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Modules\Prospects\Jobs\ExecuteSyncJob;
use Modules\Prospects\Jobs\MasterSyncJob;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class SyncHistoryResource extends Resource
{
    protected static ?string $model = SyncHistory::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::ArrowPathRoundedSquare;

    public static function getNavigationGroup(): ?string
    {
        return __('prospects::resource.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('prospects::resource.sync_history.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('prospects::resource.sync_history.plural_model_label');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('command')
                    ->label(__('prospects::resource.fields.command'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => str(str_replace('prospects:', '', $state))->replace('sync-', '')->title()),
                
                TextColumn::make('status')
                    ->label(__('prospects::resource.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'running' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'running' => __('prospects::resource.options.status.running'),
                        'completed' => __('prospects::resource.options.status.completed'),
                        'failed' => __('prospects::resource.options.status.failed'),
                        default => $state,
                    }),

                TextColumn::make('user.name')
                    ->label(__('prospects::resource.fields.started_by'))
                    ->placeholder('System/Cron')
                    ->sortable(),

                TextColumn::make('records_count')
                    ->label(__('prospects::resource.fields.items'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('started_at')
                    ->label(__('prospects::resource.fields.started_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('finished_at')
                    ->label(__('prospects::resource.fields.finished_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('started_at', 'desc')
            ->poll('5s')
            ->headerActions([
                Action::make('sync_master')
                    ->label(__('prospects::resource.actions.sync_master.label'))
                    ->icon(Heroicon::Bolt)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function () {
                        $history = SyncHistory::create([
                            'command' => 'prospects:sync-master',
                            'type' => 'master',
                            'status' => 'running',
                            'started_at' => now(),
                            'user_id' => auth()->id(),
                            'logs' => [[
                                'time' => now()->format('H:i:s'),
                                'message' => __('prospects::resource.sync_history.logs.master_requested'),
                                'type' => 'info',
                                'icon' => '🚀',
                            ]],
                        ]);

                        MasterSyncJob::dispatch(auth()->id(), $history->id);

                        Notification::make()
                            ->title(__('prospects::resource.notifications.master_sync_started.title'))
                            ->info()
                            ->body(__('prospects::resource.notifications.master_sync_started.body'))
                            ->send();
                    })
                    ->visible(false),

                ActionGroup::make([
                    Action::make('sync_rbfa')
                        ->label(__('prospects::resource.actions.individual_sync.rbfa'))
                        ->color('success')
                        ->action(fn () => self::runSync('prospects:sync-rbfa-graphql')),

                    Action::make('sync_lbfa')
                        ->label(__('prospects::resource.actions.individual_sync.lbfa'))
                        ->action(fn () => self::runSync('prospects:sync-lbfa-clubs')),
                    
                    Action::make('sync_val')
                        ->label(__('prospects::resource.actions.individual_sync.val'))
                        ->action(fn () => self::runSync('prospects:sync-val-clubs')),

                    Action::make('sync_hockey')
                        ->label(__('prospects::resource.actions.individual_sync.hockey'))
                        ->action(fn () => self::runSync('prospects:sync-hockey-clubs')),

                    Action::make('sync_tpv')
                        ->label(__('prospects::resource.actions.individual_sync.tpv'))
                        ->action(fn () => self::runSync('prospects:sync-tpv-clubs')),
                    
                    Action::make('sync_aft')
                        ->label(__('prospects::resource.actions.individual_sync.aft'))
                        ->action(fn () => self::runSync('prospects:sync-aft-clubs')),
                ])
                ->label(__('prospects::resource.actions.individual_sync.label'))
                ->icon('heroicon-o-play-circle')
                ->color('gray')
                ->visible(false),
            ])

            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\Action::make('mark_failed')
                    ->label(__('prospects::resource.actions.mark_failed.label'))
                    ->icon(Heroicon::XCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'running')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'failed',
                            'finished_at' => now(),
                        ]);
                        
                        $logs = $record->logs ?? [];
                        $logs[] = [
                            'time' => now()->format('H:i:s'),
                            'message' => __('prospects::resource.sync_history.logs.manually_failed'),
                            'type' => 'error',
                            'icon' => '🛑',
                        ];
                        $record->logs = $logs;
                        $record->save();

                        Notification::make()
                            ->title(__('prospects::resource.notifications.manually_failed.title'))
                            ->danger()
                            ->send();
                    }),
                \Filament\Actions\Action::make('mark_completed')
                    ->label(__('prospects::resource.actions.mark_completed.label'))
                    ->icon(Heroicon::CheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'running')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'completed',
                            'finished_at' => now(),
                        ]);

                        $logs = $record->logs ?? [];
                        $logs[] = [
                            'time' => now()->format('H:i:s'),
                            'message' => __('prospects::resource.sync_history.logs.manually_completed'),
                            'type' => 'success',
                            'icon' => '✅',
                        ];
                        $record->logs = $logs;
                        $record->save();

                        Notification::make()
                            ->title(__('prospects::resource.notifications.manually_completed.title'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function runSync(string $command): void
    {
        ExecuteSyncJob::dispatch($command, auth()->id());
        
        Notification::make()
            ->title(__('prospects::resource.notifications.sync_started.title'))
            ->info()
            ->body(__('prospects::resource.notifications.sync_started.body', ['command' => $command]))
            ->send();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('prospects::resource.sections.sync_details'))
                    ->components([
                        Grid::make(3)->components([
                            TextEntry::make('command')
                                ->label(__('prospects::resource.fields.command')),
                            TextEntry::make('status')
                                ->label(__('prospects::resource.fields.status'))
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'running' => 'warning',
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    default => 'gray',
                                }),
                            TextEntry::make('records_count')
                                ->label(__('prospects::resource.fields.total_items')),
                        ]),
                        Grid::make(2)->components([
                            TextEntry::make('started_at')
                                ->label(__('prospects::resource.fields.started_at'))
                                ->dateTime(),
                            TextEntry::make('finished_at')
                                ->label(__('prospects::resource.fields.finished_at'))
                                ->dateTime(),
                        ]),
                    ]),

                Section::make(__('prospects::resource.sections.logs'))
                    ->description(__('prospects::resource.sections.logs_desc'))
                    ->components([
                        RepeatableEntry::make('logs')
                            ->label(false)
                            ->components([
                                Grid::make(10)->components([
                                    TextEntry::make('icon')
                                        ->label(false)
                                        ->columnSpan(1)
                                        ->extraAttributes(['class' => 'text-lg']),
                                    
                                    TextEntry::make('timestamp')
                                        ->label(false)
                                        ->columnSpan(2)
                                        ->dateTime('H:i:s')
                                        ->color('gray')
                 ->visible(false),

                                    TextEntry::make('message')
                                        ->label(false)
                                        ->columnSpan(6),

                                    TextEntry::make('type')
                                        ->label(false)
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'error' => 'danger',
                                            'warning' => 'warning',
                                            'info' => 'info',
                                            default => 'gray',
                                        })
                                        ->columnSpan(1),
                                ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn ($record) => empty($record->logs)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \Modules\Prospects\Filament\Resources\SyncHistoryResource\Pages\ListSyncHistories::route('/'),
        ];
    }
}
