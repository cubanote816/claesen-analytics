<?php

namespace Modules\Prospects\Filament\Resources\Prospects;

use Modules\Prospects\Filament\Resources\Prospects\Pages\ManageProspects;
use Modules\Prospects\Models\Prospect;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkAction;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Support\CampaignSendTracker;
use Modules\Mailing\Enums\AudienceType;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\TemplateCategory;
use Modules\Mailing\Filament\Resources\CampaignResource;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\ToggleColumn;

class ProspectResource extends Resource
{
    protected static ?string $model = Prospect::class;

    // CLA-581: see Performance\ProjectResource — same reasoning, exclude technician from the default-allow.
    public static function canAccess(): bool
    {
        return ! (auth()->user()?->hasRole('technician') ?? false);
    }

    public static function getModelLabel(): string
    {
        return __('prospects::resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('prospects::resource.plural_model_label');
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.growth_acquisition');
    }

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('prospects::resource.sections.club_info'))
                    ->components([
                        TextInput::make('name')
                            ->label(__('prospects::resource.fields.name'))
                            ->required(),
                        Select::make('region_id')
                            ->label(__('prospects::resource.fields.region'))
                            ->relationship('region', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('federation')
                            ->label(__('prospects::resource.fields.federation'))
                            ->options([
                                'RBFA' => 'Voetbal (RBFA)',
                                'VL-VV' => 'Voetbal (VL-VV)',
                                'FR-ACFF' => 'Football (FR-ACFF)',
                                'VL-VAL' => 'Atletiek (VAL)',
                                'FR-LBFA' => 'Atletiek (LBFA)',
                                'VL-TPV' => 'Tennis & Padel (TPV)',
                                'FR-AFT' => 'Tennis (AFT)',
                                'VL-VHL' => 'Hockey (VHL)',
                                'FR-LFH' => 'Hockey (LFH)',
                                'ARBH-KBHB' => 'Hockey (ARBH)',
                            ])
                            ->required(),
                        Select::make('language')
                            ->label(__('prospects::resource.fields.language'))
                            ->options([
                                'nl' => __('prospects::resource.options.languages.nl'),
                                'fr' => __('prospects::resource.options.languages.fr'),
                                'en' => __('prospects::resource.options.languages.en'),
                            ])
                            ->required(),
                        TextInput::make('contact_person')
                            ->label(__('prospects::resource.fields.contact_person')),
                        TextInput::make('channel')
                            ->label(__('prospects::resource.fields.channel')),
                        TextInput::make('website')
                            ->label(__('prospects::resource.fields.website'))
                            ->url(),
                        TextInput::make('vat_number')
                            ->label(__('prospects::resource.fields.vat_number')),
                        TextInput::make('cafca_relation_id')
                            ->label(__('prospects::resource.fields.cafca_id')),
                        Toggle::make('is_tester')
                            ->label(__('prospects::resource.fields.is_tester'))
                            ->helperText(__('prospects::resource.fields.is_tester_helper'))
                            ->default(false),
                    ]),
                Section::make(__('prospects::resource.sections.marketing_targets'))
                    ->components([
                        Repeater::make('locations')
                            ->hiddenLabel()
                            ->relationship()
                            ->components([
                                Select::make('contact_type')
                                    ->label(__('prospects::resource.fields.contact_type'))
                                    ->options([
                                        'headquarters' => __('prospects::resource.options.contact_types.headquarters'),
                                        'stadium' => __('prospects::resource.options.contact_types.stadium'),
                                        'venue_name' => __('prospects::resource.options.contact_types.venue_name'),
                                        'club_house' => __('prospects::resource.options.contact_types.club_house'),
                                        'contact_person' => __('prospects::resource.options.contact_types.contact_person'),
                                        'other' => __('prospects::resource.options.contact_types.other'),
                                    ])
                                    ->required()
                                    ->columnSpanFull(),
                                TextInput::make('contact_name')
                                    ->label(__('prospects::resource.fields.contact_name'))
                                    ->columnSpanFull(),
                                TextInput::make('email')
                                    ->label(__('prospects::resource.fields.email'))
                                    ->email()
                                    // prospects_locations.email is uniquely indexed; validate it
                                    // here so a duplicate shows a form error instead of a 500
                                    // UniqueConstraintViolation. ignoreRecord lets a location keep
                                    // its own email on edit.
                                    ->unique(
                                        table: 'prospects_locations',
                                        column: 'email',
                                        ignoreRecord: true,
                                    )
                                    ->columnSpanFull(),
                                TextInput::make('phone')
                                    ->label(__('prospects::resource.fields.phone'))
                                    ->columnSpanFull(),
                                Textarea::make('address')
                                    ->label(__('prospects::resource.fields.address'))
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('prospects::resource.sections.club_info'))
                    ->components([
                        ImageEntry::make('logo_url')
                            ->label(__('prospects::resource.fields.logo'))
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->height(100),
                        Grid::make(2)->components([
                            TextEntry::make('name')
                                ->label(__('prospects::resource.fields.name'))
                                ->weight('bold')
                                ->size('lg'),
                            TextEntry::make('website')
                                ->label(__('prospects::resource.fields.website'))
                                ->url(fn($record) => $record->website, true)
                                ->visible(fn($state) => filled($state)),
                            TextEntry::make('region.name')
                                ->label(__('prospects::resource.fields.region')),
                            TextEntry::make('federation')
                                ->label(__('prospects::resource.fields.federation'))
                                ->badge()
                                ->color(fn(string $state): string => match ($state) {
                                    'RBFA' => 'success',
                                    'VL-VV' => 'success',
                                    'FR-ACFF' => 'success',
                                    'VAL' => 'warning',
                                    'VL-VAL' => 'warning',
                                    'LBFA' => 'info',
                                    'FR-LBFA' => 'info',
                                    'TPV' => 'success',
                                    'VL-TPV' => 'success',
                                    'AFT' => 'danger',
                                    'FR-AFT' => 'danger',
                                    'VHL' => 'info',
                                    'VL-VHL' => 'info',
                                    'LFH' => 'warning',
                                    'FR-LFH' => 'warning',
                                    'ARBH-KBHB' => 'success',
                                    default => 'gray',
                                }),
                            TextEntry::make('language')
                                ->label(__('prospects::resource.fields.language'))
                                ->formatStateUsing(fn(string $state): string => match ($state) {
                                    'nl' => __('prospects::resource.options.languages.nl'),
                                    'fr' => __('prospects::resource.options.languages.fr'),
                                    'en' => __('prospects::resource.options.languages.en'),
                                    default => $state,
                                }),
                            TextEntry::make('contact_person')
                                ->label(__('prospects::resource.fields.contact_person'))
                                ->visible(fn($state) => filled($state)),
                            TextEntry::make('channel')
                                ->label(__('prospects::resource.fields.channel'))
                                ->visible(fn($state) => filled($state)),
                            TextEntry::make('vat_number')
                                ->label(__('prospects::resource.fields.vat_number'))
                                ->visible(fn($state) => filled($state)),
                            TextEntry::make('cafca_relation_id')
                                ->label(__('prospects::resource.fields.cafca_id'))
                                ->visible(fn($state) => filled($state)),
                        ]),
                    ]),
                Section::make(__('prospects::resource.sections.marketing_targets'))
                    ->components([
                        RepeatableEntry::make('locations')
                            ->hiddenLabel()
                            ->components([
                                TextEntry::make('contact_type')
                                    ->label(__('prospects::resource.fields.contact_type'))
                                    ->badge()
                                    ->color('info')
                                    ->formatStateUsing(fn(string $state): string => match ($state) {
                                        'headquarters' => __('prospects::resource.options.contact_types.headquarters'),
                                        'stadium' => __('prospects::resource.options.contact_types.stadium'),
                                        'venue_name' => __('prospects::resource.options.contact_types.venue_name'),
                                        'club_house' => __('prospects::resource.options.contact_types.club_house'),
                                        'contact_person' => __('prospects::resource.options.contact_types.contact_person'),
                                        'other' => __('prospects::resource.options.contact_types.other'),
                                        default => $state,
                                    })
                                    ->columnSpanFull(),
                                TextEntry::make('email')
                                    ->label(__('prospects::resource.fields.email'))
                                    ->icon('heroicon-m-envelope')
                                    ->columnSpanFull()
                                    ->visible(fn($state) => filled($state)),
                                TextEntry::make('contact_name')
                                    ->label(__('prospects::resource.fields.contact_name'))
                                    ->icon('heroicon-m-user')
                                    ->columnSpanFull()
                                    ->visible(fn($state) => filled($state)),
                                TextEntry::make('phone')
                                    ->label(__('prospects::resource.fields.phone'))
                                    ->icon('heroicon-m-phone')
                                    ->columnSpanFull()
                                    ->visible(fn($state) => filled($state)),
                                TextEntry::make('address')
                                    ->label(__('prospects::resource.fields.address'))
                                    ->icon('heroicon-m-map-pin')
                                    ->columnSpanFull()
                                    ->visible(fn($state) => filled($state)),
                            ])
                            ->columns(1),
                    ]),
            ]);
    }

    /** CLA-668: federation code → readable label. Unknown codes are shown as-is. */
    private const FEDERATION_LABELS = [
        'RBFA' => 'Voetbal (RBFA)',
        'VL-VV' => 'Voetbal Vlaanderen (VL-VV)',
        'FR-ACFF' => 'Football ACFF (FR-ACFF)',
        'VAL' => 'Vlaamse Atletiekliga (VAL)',
        'VL-VAL' => 'Vlaamse Atletiekliga (VL-VAL)',
        'LBFA' => 'Ligue Belge Francophone d\'Athlétisme (LBFA)',
        'FR-LBFA' => 'Ligue Belge Francophone d\'Athlétisme (FR-LBFA)',
        'TPV' => 'Tennis & Padel Vlaanderen (TPV)',
        'VL-TPV' => 'Tennis & Padel Vlaanderen (VL-TPV)',
        'AFT' => 'Association Francophone de Tennis (AFT)',
        'FR-AFT' => 'Association Francophone de Tennis (FR-AFT)',
        'FR-AFTT' => 'Association Francophone de Tennis de Table (FR-AFTT)',
        'VHL' => 'Vlaamse Hockey Liga (VHL)',
        'VL-VHL' => 'Vlaamse Hockey Liga (VL-VHL)',
        'LFH' => 'Ligue Francophone de Hockey (LFH)',
        'FR-LFH' => 'Ligue Francophone de Hockey (FR-LFH)',
        'ARBH-KBHB' => 'Koninklijke Belgische Hockeybond (ARBH-KBHB)',
        'BR-CAD' => 'Brussels Cadastre (BR-CAD)',
    ];

    public static function sportLabel(string $type): string
    {
        $key = "prospects::resource.options.sport_types.{$type}";

        return Lang::has($key) ? __($key) : Str::of($type)->replace('_', ' ')->lower()->ucfirst()->toString();
    }

    public static function federationLabel(string $code): string
    {
        return self::FEDERATION_LABELS[$code] ?? $code;
    }

    /** @return array<string, string> sport types present in the data, keyed by raw value. */
    public static function sportFilterOptions(): array
    {
        return Prospect::query()->whereNotNull('type')->where('type', '!=', '')
            ->distinct()->orderBy('type')->pluck('type')
            ->mapWithKeys(fn (string $type): array => [$type => static::sportLabel($type)])
            ->all();
    }

    /** @return array<string, string> federations present in the data (for the given sport, if any). */
    public static function federationFilterOptions(?string $type = null): array
    {
        return Prospect::query()->whereNotNull('federation')->where('federation', '!=', '')
            ->when(filled($type), fn (Builder $query): Builder => $query->where('type', $type))
            ->distinct()->orderBy('federation')->pluck('federation')
            ->mapWithKeys(fn (string $code): array => [$code => static::federationLabel($code)])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_url')
                    ->label(__('prospects::resource.fields.logo'))
                    ->circular(),
                TextColumn::make('name')
                    ->label(__('prospects::resource.fields.name'))
                    ->searchable(),
                TextColumn::make('region.name')
                    ->label(__('prospects::resource.fields.region'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('federation')
                    ->label(__('prospects::resource.fields.federation'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'RBFA' => 'success',
                        'VL-VV' => 'success',
                        'FR-ACFF' => 'success',
                        'VAL' => 'warning',
                        'VL-VAL' => 'warning',
                        'LBFA' => 'info',
                        'FR-LBFA' => 'info',
                        'TPV' => 'success',
                        'VL-TPV' => 'success',
                        'AFT' => 'danger',
                        'FR-AFT' => 'danger',
                        'VHL' => 'info',
                        'VL-VHL' => 'info',
                        'LFH' => 'warning',
                        'FR-LFH' => 'warning',
                        'ARBH-KBHB' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('language')
                    ->label(__('prospects::resource.fields.language'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('channel')
                    ->label(__('prospects::resource.fields.channel'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('website')
                    ->label(__('prospects::resource.fields.website')),
                // Searchable by a location's email (stored on the locations relationship,
                // not on the prospect row). Hidden by default to keep the table compact;
                // the global search box still matches it via the whereHas query.
                TextColumn::make('primary_email')
                    ->label(__('prospects::resource.fields.email'))
                    ->state(fn (Prospect $record): ?string => $record->locations->firstWhere(fn ($loc): bool => filled($loc->email))?->email)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('locations', fn (Builder $q) => $q->where('email', 'like', "%{$search}%"))),
                IconColumn::make('has_email')
                    ->label(__('prospects::resource.fields.email'))
                    ->boolean()
                    ->state(fn (Prospect $record): bool => $record->locations_exists_with_email),
                TextColumn::make('locations_count')
                    ->counts('locations')
                    ->label(__('prospects::resource.fields.locations_count')),
                TextColumn::make('unsubscribed_at')
                    ->label(__('prospects::resource.fields.unsubscribed_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('danger')
                    ->placeholder(__('prospects::resource.options.status.active')),
                ToggleColumn::make('is_tester')
                    ->label(__('prospects::resource.fields.tester'))
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('has_email')
                    ->label(__('prospects::resource.fields.has_email'))
                    ->queries(
                        true: fn(Builder $query) => $query->whereHas('locations', fn($q) => $q->whereNotNull('email')->where('email', '!=', '')),
                        false: fn(Builder $query) => $query->whereDoesntHave('locations', fn($q) => $q->whereNotNull('email')->where('email', '!=', '')),
                        blank: fn(Builder $query) => $query,
                    )
                    ->native(false),
                TernaryFilter::make('unsubscribed')
                    ->label(__('prospects::resource.fields.unsubscribed_at'))
                    ->placeholder(__('prospects::resource.options.status.all'))
                    ->trueLabel(__('prospects::resource.options.status.unsubscribed'))
                    ->falseLabel(__('prospects::resource.options.status.active'))
                    ->queries(
                        true: fn(Builder $query) => $query->whereNotNull('unsubscribed_at'),
                        false: fn(Builder $query) => $query->whereNull('unsubscribed_at'),
                        blank: fn(Builder $query) => $query,
                    )
                    ->native(false),
                Filter::make('region_filter')
                    ->form([
                        Select::make('region_id')
                            ->label(__('prospects::resource.fields.region'))
                            ->options(\Modules\Prospects\Models\Region::pluck('name', 'id'))
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['region_id'],
                                fn(Builder $query, $regionId): Builder => $query->where('region_id', $regionId),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['region_id'] ?? null) {
                            $regionName = \Modules\Prospects\Models\Region::find($data['region_id'])?->name;
                            $indicators['region'] = __('prospects::resource.fields.region') . ': ' . $regionName;
                        }
                        return $indicators;
                    }),
                Filter::make('sport_federation')
                    ->columnSpanFull()
                    ->form([
                        Select::make('type')
                            ->label(__('prospects::resource.fields.type_sport'))
                            ->options(fn (): array => static::sportFilterOptions())
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                // A federation only makes sense for the sport it belongs to.
                                $federation = $get('federation');
                                if (filled($federation) && ! array_key_exists($federation, static::federationFilterOptions($state))) {
                                    $set('federation', null);
                                }
                            }),
                        Select::make('federation')
                            ->label(__('prospects::resource.fields.federation'))
                            ->options(fn (Get $get): array => static::federationFilterOptions($get('type')))
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                if (blank($state) || filled($get('type'))) {
                                    return;
                                }

                                $types = Prospect::query()->where('federation', $state)->whereNotNull('type')->distinct()->pluck('type');
                                if ($types->count() === 1) {
                                    $set('type', $types->first());
                                }
                            }),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(filled($data['type'] ?? null), fn (Builder $query): Builder => $query->where('type', $data['type']))
                            ->when(filled($data['federation'] ?? null), fn (Builder $query): Builder => $query->where('federation', $data['federation']));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (filled($data['type'] ?? null)) {
                            $indicators['type'] = __('prospects::resource.fields.type_sport') . ': ' . static::sportLabel($data['type']);
                        }
                        if (filled($data['federation'] ?? null)) {
                            $indicators['federation'] = __('prospects::resource.fields.federation') . ': ' . static::federationLabel($data['federation']);
                        }
                        return $indicators;
                    }),
            ])
            ->filtersLayout(FiltersLayout::Dropdown)
            ->filtersFormColumns(1)
            ->deferFilters(false)
            ->recordActions([
                ViewAction::make()
                    ->label(fn () => new \Illuminate\Support\HtmlString('<span class="hidden xl:inline">' . __('filament-actions::view.single.label') . '</span>')),
                EditAction::make()
                    ->label(fn () => new \Illuminate\Support\HtmlString('<span class="hidden xl:inline">' . __('filament-actions::edit.single.label') . '</span>')),
                DeleteAction::make()
                    ->label(fn () => new \Illuminate\Support\HtmlString('<span class="hidden xl:inline">' . __('filament-actions::delete.single.label') . '</span>')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('execute_campaign')
                        ->label(__('prospects::resource.actions.execute_campaign.label'))
                        ->icon('heroicon-o-rocket-launch')
                        ->color('primary')
                        ->visible(fn (): bool => auth()->user()?->can('create', Campaign::class) ?? false)
                        ->form([
                            TextInput::make('description')
                                ->label(__('prospects::resource.actions.execute_campaign.form.description'))
                                ->placeholder(__('prospects::resource.actions.execute_campaign.form.description_placeholder'))
                                ->required(),

                            Select::make('template_id')
                                ->label(__('prospects::resource.actions.execute_campaign.form.template'))
                                ->options(\Modules\Mailing\Models\EmailTemplate::query()->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data, $livewire) {
                            $userId = \Filament\Facades\Filament::auth()->id() 
                                ?? auth()->guard('filament.admin')->id() 
                                ?? auth()->id() 
                                ?? auth()->user()?->getAuthIdentifier();

                            $unsubscribedCount = $records->filter(fn ($p) => !empty($p->unsubscribed_at))->count();
                            $totalCount = $records->count();
                            $subscribedCount = $totalCount - $unsubscribedCount;

                            if ($subscribedCount === 0) {
                                $key = $totalCount === 0
                                    ? 'no_prospects_selected'
                                    : 'all_prospects_unsubscribed';

                                Notification::make()
                                    ->title(__("prospects::resource.notifications.{$key}.title"))
                                    ->body(__("prospects::resource.notifications.{$key}.body"))
                                    ->danger()
                                    ->send();

                                $livewire->deselectAllTableRecords();
                                return;
                            }

                            // Verificar si al menos uno tiene email (Verificamos en las locaciones de los SUBCRITOS)
                            $hasEmails = $records->filter(fn($p) => empty($p->unsubscribed_at))
                                ->contains(fn ($p) => $p->locations()->whereNotNull('email')->where('email', '!=', '')->exists());

                            if (!$hasEmails) {
                                Notification::make()
                                    ->title(__('prospects::resource.notifications.no_emails_found.title'))
                                    ->body(__('prospects::resource.notifications.no_emails_found.body'))
                                    ->warning()
                                    ->send();
                                
                                // Importante: deseleccionar aunque falle para resetear el estado UI
                                $livewire->deselectAllTableRecords();
                                return;
                            }

                            if ($unsubscribedCount > 0) {
                                Notification::make()
                                    ->title(__('prospects::resource.notifications.partial_skip.title'))
                                    ->warning()
                                    ->persistent()
                                    ->body(__('prospects::resource.notifications.partial_skip.body', [
                                        'unsubscribed' => $unsubscribedCount,
                                        'total' => $totalCount,
                                    ]))
                                    ->send();
                            }

                            if (auth()->user()?->can('sendImmediately', Campaign::class)) {
                                static::sendCampaignNow($records, $data, $userId);
                                // Wake the panel-wide watcher so it starts polling without a page reload (CLA-680).
                                $livewire->dispatch('campaign-send-tracked');
                            } elseif (! static::sendCampaignToReview($records, $data, $userId)) {
                                $livewire->deselectAllTableRecords();
                                return;
                            }

                            // Asegurar la limpieza inmediata para el FAB
                            $livewire->deselectAllTableRecords();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Approver path: mail the selected prospects right away (inline ExecuteCampaignJob).
     */
    private static function sendCampaignNow(Collection $records, array $data, $userId): void
    {
        \Illuminate\Support\Facades\Log::info("Dispatching Prospect Mailing Job", [
            'user_id' => $userId,
            'prospect_count' => $records->count(),
            'description' => $data['description'],
        ]);

        $job = new ExecuteCampaignJob(
            $records->pluck('id')->toArray(),
            $data['template_id'],
            $userId,
            $data['description']
        );
        dispatch($job);

        // CLA-680: the job creates the campaign, so track it by its dispatch key.
        CampaignSendTracker::trackDispatch($job->dispatchKey);

        Notification::make()
            ->title(__('prospects::resource.notifications.campaign_started.title'))
            ->success()
            ->persistent()
            ->body(__('prospects::resource.notifications.campaign_started.body', [
                'count' => $records->filter(fn ($p) => empty($p->unsubscribed_at))->count(),
            ]))
            ->send();
    }

    /**
     * Review path: create a manual-audience campaign in REVIEW; nothing is sent.
     * Returns false (and creates nothing) when the template cannot be used.
     */
    private static function sendCampaignToReview(Collection $records, array $data, $userId): bool
    {
        $template = EmailTemplate::findOrFail($data['template_id']);

        try {
            $snapshot = Campaign::buildSnapshotFrom($template);

            // Mirrors the APPROVED check in Campaign::transitionTo
            if ($snapshot['template_category_snapshot'] === TemplateCategory::COMMERCIAL->value
                && ! in_array($snapshot['preference_category_snapshot'], array_keys(config('mailing.preference_categories', [])), true)) {
                throw new \InvalidArgumentException(
                    "Commercial campaigns require a valid preference category. Edit template '{$template->name}' to assign one."
                );
            }
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title(__('prospects::resource.notifications.campaign_template_invalid.title'))
                ->body(__('prospects::resource.notifications.campaign_template_invalid.body', ['reason' => $e->getMessage()]))
                ->danger()
                ->send();

            return false;
        }

        $campaign = DB::transaction(function () use ($records, $data, $userId, $template, $snapshot) {
            $campaign = Campaign::create([
                ...$snapshot,
                'description' => $data['description'],
                'template_id' => $template->id,
                'audience_type' => AudienceType::MANUAL,
                'audience_filters' => [
                    'prospect_ids' => $records->filter(fn ($p) => empty($p->unsubscribed_at))
                        ->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                ],
                'status' => CampaignStatus::DRAFT,
                'created_by' => $userId,
                'total_count' => 0,
            ]);

            $campaign->transitionTo(CampaignStatus::REVIEW);

            return $campaign;
        });

        Notification::make()
            ->title(__('prospects::resource.notifications.campaign_sent_to_review.title'))
            ->body(__('prospects::resource.notifications.campaign_sent_to_review.body'))
            ->success()
            ->actions([
                Action::make('view')
                    ->label(__('prospects::resource.actions.execute_campaign.view_campaign'))
                    ->url(CampaignResource::getUrl('view', ['record' => $campaign])),
            ])
            ->send();

        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['region'])
            ->withExists(['locations as locations_exists_with_email' => function (Builder $query) {
                $query->whereNotNull('email')->where('email', '!=', '');
            }]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProspects::route('/'),
        ];
    }
}
