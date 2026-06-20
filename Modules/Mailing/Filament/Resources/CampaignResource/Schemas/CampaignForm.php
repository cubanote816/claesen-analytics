<?php

namespace Modules\Mailing\Filament\Resources\CampaignResource\Schemas;

use Carbon\Carbon;
use Filament\Actions\Action as FormAction;
use Filament\Schemas\Components\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Mailing\Enums\AudienceType;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\FollowUpTrigger;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Services\SegmentResolverService;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('mailing::resource.sections.campaign_details'))
                    ->columns(2)
                    ->components([
                        Select::make('template_id')
                            ->label(__('mailing::resource.fields.template'))
                            ->options(EmailTemplate::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?int $state, Set $set): void {
                                if (! $template = EmailTemplate::find($state)) {
                                    return;
                                }

                                try {
                                    $snapshot = Campaign::buildSnapshotFrom($template);
                                } catch (\InvalidArgumentException $e) {
                                    Notification::make()
                                        ->title(__('mailing::resource.notifications.template_invalid_pref_category'))
                                        ->body($e->getMessage())
                                        ->warning()
                                        ->send();
                                    return;
                                }

                                $set('template_name', $snapshot['template_name']);
                                $set('subject_snapshot', $snapshot['subject_snapshot']);
                                $set('body_snapshot', $snapshot['body_snapshot']);
                                $set('template_category_snapshot', $snapshot['template_category_snapshot']);
                                $set('preference_category_snapshot', $snapshot['preference_category_snapshot']);
                            })
                            ->columnSpanFull(),

                        TextInput::make('description')
                            ->label(__('mailing::resource.fields.description'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('subject_snapshot')
                            ->label(__('mailing::resource.fields.subject'))
                            ->required()
                            ->maxLength(255)
                            ->helperText(__('mailing::resource.fields.subject_helper'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('mailing::resource.sections.ab_test'))
                    ->columns(2)
                    ->collapsed()
                    ->components([
                        TextInput::make('ab_subject_b')
                            ->label(__('mailing::resource.fields.ab_subject_b'))
                            ->helperText(__('mailing::resource.fields.ab_subject_b_helper'))
                            ->maxLength(255)
                            ->nullable()
                            ->columnSpanFull(),

                        TextInput::make('ab_split_percent')
                            ->label(__('mailing::resource.fields.ab_split_percent'))
                            ->helperText(__('mailing::resource.fields.ab_split_percent_helper'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(10)
                            ->visible(fn (Get $get): bool => (bool) $get('ab_subject_b')),

                        TextInput::make('ab_winner_after_hours')
                            ->label(__('mailing::resource.fields.ab_winner_after_hours'))
                            ->numeric()
                            ->minValue(1)
                            ->default(4)
                            ->visible(fn (Get $get): bool => (bool) $get('ab_subject_b')),
                    ]),

                Section::make(__('mailing::resource.sections.audience'))
                    ->columns(1)
                    ->components([
                        Select::make('audience_type')
                            ->label(__('mailing::resource.fields.audience_type'))
                            ->options(collect(AudienceType::cases())->mapWithKeys(
                                fn (AudienceType $t) => [$t->value => $t->label()]
                            ))
                            ->default(AudienceType::ALL_SUBSCRIBED->value)
                            ->required()
                            ->live(),

                        Select::make('audience_filters.operator')
                            ->label(__('mailing::resource.fields.segment_operator'))
                            ->options([
                                'AND' => __('mailing::resource.fields.segment_operator_and'),
                                'OR'  => __('mailing::resource.fields.segment_operator_or'),
                            ])
                            ->default('AND')
                            ->visible(fn (Get $get): bool => $get('audience_type') === AudienceType::SEGMENT->value),

                        Repeater::make('audience_filters.rules')
                            ->label(__('mailing::resource.fields.segment_rules'))
                            ->schema(self::ruleSchema())
                            ->addActionLabel(__('mailing::resource.actions.add_rule'))
                            ->defaultItems(0)
                            ->collapsible()
                            ->visible(fn (Get $get): bool => $get('audience_type') === AudienceType::SEGMENT->value),

                        DateTimePicker::make('scheduled_at')
                            ->label(__('mailing::resource.fields.scheduled_at'))
                            ->helperText(__('mailing::resource.fields.scheduled_at_helper'))
                            ->timezone('Europe/Brussels')
                            ->displayFormat('d/m/Y H:i')
                            ->minDate(now('Europe/Brussels'))
                            ->nullable()
                            ->dehydrateStateUsing(function (?string $state): ?string {
                                if ($state === null) {
                                    return null;
                                }
                                // Guarantee UTC storage regardless of Filament version behavior
                                return Carbon::parse($state, 'Europe/Brussels')->utc()->toDateTimeString();
                            }),

                        Select::make('timezone')
                            ->label(__('mailing::resource.fields.timezone'))
                            ->options(['Europe/Brussels' => 'Europe/Brussels (CET/CEST)'])
                            ->default('Europe/Brussels')
                            ->disabled(),

                        Actions::make([
                            FormAction::make('preview_audience')
                                ->label(__('mailing::resource.actions.preview_audience'))
                                ->icon('heroicon-o-users')
                                ->color('gray')
                                ->action(function (Get $get): void {
                                    $filters = [
                                        'operator' => $get('audience_filters.operator') ?? 'AND',
                                        'rules'    => $get('audience_filters.rules') ?? [],
                                    ];

                                    try {
                                        $count = app(SegmentResolverService::class)->count($filters);

                                        Notification::make()
                                            ->title(__('mailing::resource.notifications.audience_preview', ['count' => $count]))
                                            ->success()
                                            ->send();
                                    } catch (\InvalidArgumentException $e) {
                                        Notification::make()
                                            ->title(__('mailing::resource.notifications.segment_error'))
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                }),
                        ])
                        ->visible(fn (Get $get): bool => $get('audience_type') === AudienceType::SEGMENT->value),
                    ]),

                Section::make(__('mailing::resource.sections.followup'))
                    ->columns(2)
                    ->collapsed()
                    ->components([
                        Select::make('followup_campaign_id')
                            ->label(__('mailing::resource.fields.followup_campaign'))
                            ->helperText(__('mailing::resource.fields.followup_campaign_helper'))
                            ->options(fn ($record) => Campaign::where('status', CampaignStatus::APPROVED->value)
                                ->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                                ->pluck('description', 'id')
                            )
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->columnSpanFull(),

                        Select::make('followup_trigger')
                            ->label(__('mailing::resource.fields.followup_trigger'))
                            ->options(collect(FollowUpTrigger::cases())
                                ->mapWithKeys(fn (FollowUpTrigger $t) => [$t->value => $t->label()])
                            )
                            ->nullable()
                            ->visible(fn (Get $get): bool => (bool) $get('followup_campaign_id')),

                        TextInput::make('followup_delay_hours')
                            ->label(__('mailing::resource.fields.followup_delay_hours'))
                            ->helperText(__('mailing::resource.fields.followup_delay_helper'))
                            ->numeric()
                            ->minValue(1)
                            ->default(24)
                            ->visible(fn (Get $get): bool => (bool) $get('followup_campaign_id')),
                    ]),

                Hidden::make('template_name'),
                Hidden::make('body_snapshot'),
                Hidden::make('template_category_snapshot'),
                Hidden::make('preference_category_snapshot'),
            ]);
    }

    // -------------------------------------------------------------------------
    // Repeater rule schema
    // -------------------------------------------------------------------------

    private static function ruleSchema(): array
    {
        $eventTypeOptions = collect(MessageEventType::cases())
            ->mapWithKeys(fn (MessageEventType $t) => [$t->value => $t->label()])
            ->toArray();

        $campaignOptions = Campaign::orderByDesc('created_at')
            ->limit(50)
            ->pluck('description', 'id')
            ->toArray();

        return [
            Select::make('type')
                ->label(__('mailing::resource.fields.rule_type'))
                ->options([
                    'has_event'      => __('mailing::resource.fields.rule_has_event'),
                    'has_no_event'   => __('mailing::resource.fields.rule_has_no_event'),
                    'prospect_field' => __('mailing::resource.fields.rule_prospect_field'),
                ])
                ->required()
                ->live(),

            Select::make('event_type')
                ->label(__('mailing::resource.fields.rule_event_type'))
                ->options($eventTypeOptions)
                ->required()
                ->visible(fn (Get $get): bool => in_array($get('type'), ['has_event', 'has_no_event'], true)),

            Select::make('campaign_id')
                ->label(__('mailing::resource.fields.rule_campaign'))
                ->options($campaignOptions)
                ->placeholder(__('mailing::resource.fields.rule_campaign_any'))
                ->nullable()
                ->visible(fn (Get $get): bool => in_array($get('type'), ['has_event', 'has_no_event'], true)),

            TextInput::make('within_days')
                ->label(__('mailing::resource.fields.rule_within_days'))
                ->numeric()
                ->minValue(1)
                ->nullable()
                ->placeholder('—')
                ->visible(fn (Get $get): bool => in_array($get('type'), ['has_event', 'has_no_event'], true)),

            Select::make('field')
                ->label(__('mailing::resource.fields.rule_field'))
                ->options([
                    'language'   => __('mailing::resource.fields.rule_field_language'),
                    'federation' => __('mailing::resource.fields.rule_field_federation'),
                    'region_id'  => __('mailing::resource.fields.rule_field_region'),
                ])
                ->required()
                ->visible(fn (Get $get): bool => $get('type') === 'prospect_field'),

            Select::make('operator')
                ->label(__('mailing::resource.fields.rule_operator'))
                ->options([
                    '=' => __('mailing::resource.fields.rule_operator_equals'),
                    'in' => __('mailing::resource.fields.rule_operator_in'),
                ])
                ->default('=')
                ->required()
                ->visible(fn (Get $get): bool => $get('type') === 'prospect_field'),

            TextInput::make('value')
                ->label(__('mailing::resource.fields.rule_value'))
                ->helperText(__('mailing::resource.fields.rule_value_helper'))
                ->required()
                ->visible(fn (Get $get): bool => $get('type') === 'prospect_field'),
        ];
    }
}
