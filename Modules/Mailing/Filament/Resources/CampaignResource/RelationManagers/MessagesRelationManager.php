<?php

namespace Modules\Mailing\Filament\Resources\CampaignResource\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('prospects::resource.sections.recipients');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                TextColumn::make('prospect.name')
                    ->label(__('prospects::resource.fields.prospect'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('prospects::resource.fields.email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('prospects::resource.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent'    => 'success',
                        'failed'  => 'danger',
                        'skipped' => 'warning',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'sent'    => __('prospects::resource.options.status.sent'),
                        'failed'  => __('prospects::resource.options.status.failed'),
                        'skipped' => __('prospects::resource.options.status.skipped'),
                        default   => $state,
                    }),

                TextColumn::make('sent_at')
                    ->label(__('prospects::resource.fields.sent_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('sent_at', 'desc')
            ->actions([
                ViewAction::make()
                    ->infolist(fn (Schema $schema) => $schema->components([
                        Section::make(__('prospects::resource.sections.mail_details'))
                            ->components([
                                Grid::make(2)->components([
                                    TextEntry::make('prospect.name')
                                        ->label(__('prospects::resource.fields.prospect')),
                                    TextEntry::make('email')
                                        ->label(__('prospects::resource.fields.email')),
                                    TextEntry::make('status')
                                        ->label(__('prospects::resource.fields.status'))
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'sent'    => 'success',
                                            'failed'  => 'danger',
                                            'skipped' => 'warning',
                                            default   => 'gray',
                                        }),
                                    TextEntry::make('sent_at')
                                        ->label(__('prospects::resource.fields.sent_at'))
                                        ->dateTime(),
                                    TextEntry::make('error_message')
                                        ->label(__('prospects::resource.fields.error_message'))
                                        ->hidden(fn ($record) => empty($record->error_message))
                                        ->color('danger')
                                        ->columnSpanFull(),
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
                    ])),
            ]);
    }
}
