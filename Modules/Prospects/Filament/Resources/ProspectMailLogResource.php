<?php

namespace Modules\Prospects\Filament\Resources;

use Modules\Prospects\Models\ProspectMailLog;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;

class ProspectMailLogResource extends Resource
{
    protected static ?string $model = ProspectMailLog::class;

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationGroup(): ?string
    {
        return __('prospects::resource.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('prospects::resource.mail_log.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('prospects::resource.mail_log.plural_model_label');
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
                TextColumn::make('prospect.name')
                    ->label(__('prospects::resource.fields.prospect'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('prospects::resource.fields.email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('template_name')
                    ->label(__('prospects::resource.fields.template'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label(__('prospects::resource.fields.started_by'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('prospects::resource.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'skipped' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'sent' => __('prospects::resource.options.status.sent'),
                        'failed' => __('prospects::resource.options.status.failed'),
                        'skipped' => __('prospects::resource.options.status.skipped'),
                        default => $state,
                    }),

                TextColumn::make('sent_at')
                    ->label(__('prospects::resource.fields.sent_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('sent_at', 'desc')
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('prospects::resource.sections.mail_details'))
                    ->components([
                        Grid::make(2)->components([
                            TextEntry::make('prospect.name')
                                ->label(__('prospects::resource.fields.prospect')),
                            TextEntry::make('email')
                                ->label(__('prospects::resource.fields.email')),
                            TextEntry::make('template_name')
                                ->label(__('prospects::resource.fields.template')),
                            TextEntry::make('status')
                                ->label(__('prospects::resource.fields.status'))
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'sent' => 'success',
                                    'failed' => 'danger',
                                    'skipped' => 'warning',
                                    default => 'gray',
                                }),
                            TextEntry::make('sent_at')
                                ->label(__('prospects::resource.fields.sent_at'))
                                ->dateTime(),
                            TextEntry::make('error_message')
                                ->label(__('prospects::resource.fields.error_message'))
                                ->hidden(fn ($record) => empty($record->error_message))
                                ->color('danger'),
                            TextEntry::make('user.name')
                                ->label(__('prospects::resource.fields.started_by')),
                        ]),
                    ]),

                Section::make(__('prospects::resource.sections.snapshot'))
                    ->description(__('prospects::resource.sections.snapshot_desc'))
                    ->components([
                        TextEntry::make('subject_snapshot')
                            ->label(__('prospects::resource.fields.subject')),
                        TextEntry::make('body_snapshot')
                            ->label(__('prospects::resource.fields.body'))
                            ->html(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \Modules\Prospects\Filament\Resources\ProspectMailLogResource\Pages\ListProspectMailLogs::route('/'),
            'view' => \Modules\Prospects\Filament\Resources\ProspectMailLogResource\Pages\ViewProspectMailLog::route('/{record}'),
        ];
    }
}
