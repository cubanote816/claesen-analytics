<?php

namespace Modules\Mailing\Filament\Resources\CampaignResource\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\MessageEvent;

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
            // Eager-load events so the opened/clicked/last_event_at columns
            // read from the already-loaded collection — no per-row N+1 queries.
            ->modifyQueryUsing(fn ($query) => $query->with(['events']))
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
                        'sent'          => 'success',
                        'failed'        => 'danger',
                        'skipped'       => 'warning',
                        'unsubscribed'  => 'warning',
                        default         => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'sent'          => __('prospects::resource.options.status.sent'),
                        'failed'        => __('prospects::resource.options.status.failed'),
                        'skipped'       => __('prospects::resource.options.status.skipped'),
                        'unsubscribed'  => __('prospects::resource.options.status.unsubscribed'),
                        default         => $state,
                    }),

                TextColumn::make('sent_at')
                    ->label(__('prospects::resource.fields.sent_at'))
                    ->dateTime()
                    ->sortable(),

                // Engagement columns — computed from the eager-loaded events collection.
                TextColumn::make('opened')
                    ->label(__('mailing::resource.fields.opened'))
                    ->getStateUsing(fn (CampaignMessage $record): string =>
                        $record->events->contains(
                            fn (MessageEvent $e) => $e->event_type === MessageEventType::OPENED
                        ) ? 'yes' : 'no'
                    )
                    ->badge()
                    ->color(fn (string $state): string => $state === 'yes' ? 'warning' : 'gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'yes' ? '✓' : '—')
                    ->alignCenter(),

                TextColumn::make('clicked')
                    ->label(__('mailing::resource.fields.clicked'))
                    ->getStateUsing(fn (CampaignMessage $record): string =>
                        $record->events->contains(
                            fn (MessageEvent $e) => $e->event_type === MessageEventType::CLICKED
                        ) ? 'yes' : 'no'
                    )
                    ->badge()
                    ->color(fn (string $state): string => $state === 'yes' ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'yes' ? '✓' : '—')
                    ->alignCenter(),

                TextColumn::make('last_event_at')
                    ->label(__('mailing::resource.fields.last_event_at'))
                    // events are ordered chronologically by global scope; last() is most recent.
                    ->getStateUsing(fn (CampaignMessage $record): ?string =>
                        $record->events->last()?->occurred_at?->format('d/m/Y H:i')
                    )
                    ->placeholder('—'),
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
                                            'sent'          => 'success',
                                            'failed'        => 'danger',
                                            'skipped'       => 'warning',
                                            'unsubscribed'  => 'warning',
                                            default         => 'gray',
                                        })
                                        ->formatStateUsing(fn ($state) => match ($state) {
                                            'sent'          => __('prospects::resource.options.status.sent'),
                                            'failed'        => __('prospects::resource.options.status.failed'),
                                            'skipped'       => __('prospects::resource.options.status.skipped'),
                                            'unsubscribed'  => __('prospects::resource.options.status.unsubscribed'),
                                            default         => $state,
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

                        Section::make(__('mailing::resource.sections.engagement'))
                            ->description(__('mailing::resource.sections.engagement_note'))
                            ->components([
                                RepeatableEntry::make('events')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('event_type')
                                            ->label(__('mailing::resource.fields.event_type'))
                                            ->badge()
                                            ->formatStateUsing(fn (MessageEventType $state): string =>
                                                $state->label(app()->getLocale())
                                            )
                                            ->color(fn (MessageEventType $state): string => match ($state) {
                                                MessageEventType::OPENED       => 'warning',
                                                MessageEventType::CLICKED      => 'success',
                                                MessageEventType::BOUNCED_HARD => 'danger',
                                                MessageEventType::COMPLAINED   => 'danger',
                                                MessageEventType::BOUNCED_SOFT => 'warning',
                                                default                        => 'gray',
                                            }),

                                        TextEntry::make('occurred_at')
                                            ->label(__('mailing::resource.fields.occurred_at'))
                                            ->dateTime(),

                                        TextEntry::make('link_url')
                                            ->label(__('mailing::resource.fields.link_url'))
                                            ->getStateUsing(fn (MessageEvent $record): ?string =>
                                                $record->metadata['link_url'] ?? null
                                            )
                                            ->hidden(fn ($state): bool => empty($state))
                                            ->url(fn ($state): ?string => $state)
                                            ->openUrlInNewTab()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2)
                                    ->placeholder(__('mailing::resource.sections.no_events')),
                            ]),

                        Section::make(__('prospects::resource.sections.snapshot'))
                            ->description(__('prospects::resource.sections.snapshot_desc'))
                            ->collapsed()
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
