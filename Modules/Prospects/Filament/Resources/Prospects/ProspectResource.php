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
use Modules\Prospects\Jobs\ExecuteMailingCampaignJob;
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
use Filament\Actions\Action;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;

class ProspectResource extends Resource
{
    protected static ?string $model = Prospect::class;

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
        return __('prospects::resource.navigation_group');
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
                            ->required(),
                        Select::make('federation')
                            ->label(__('prospects::resource.fields.federation'))
                            ->options([
                                'RBFA' => 'Voetbal (RBFA)',
                                'VAL' => 'Atletiek (VAL)',
                                'LBFA' => 'Atletiek (LBFA)',
                                'TPV' => 'Tennis & Padel (TPV)',
                                'AFT' => 'Tennis (AFT)',
                                'VHL' => 'Hockey (VHL)',
                                'LFH' => 'Hockey (LFH)',
                                'ARBH-KBHB' => 'Hockey (ARBH)',
                            ]),
                        Select::make('language')
                            ->label(__('prospects::resource.fields.language'))
                            ->options([
                                'nl' => __('prospects::resource.options.languages.nl'),
                                'fr' => __('prospects::resource.options.languages.fr'),
                                'en' => __('prospects::resource.options.languages.en'),
                            ]),
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
                    ]),
                Section::make(__('prospects::resource.sections.marketing_targets'))
                    ->components([
                        Repeater::make('locations')
                            ->label(__('prospects::resource.fields.locations'))
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
                                    ->required(),
                                TextInput::make('email')
                                    ->label(__('prospects::resource.fields.email'))
                                    ->email(),
                                TextInput::make('phone')
                                    ->label(__('prospects::resource.fields.phone')),
                                Textarea::make('address')
                                    ->label(__('prospects::resource.fields.address'))
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
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
                                ->url(fn($record) => $record->website, true),
                            TextEntry::make('region.name')
                                ->label(__('prospects::resource.fields.region')),
                            TextEntry::make('federation')
                                ->label(__('prospects::resource.fields.federation'))
                                ->badge()
                                ->color(fn(string $state): string => match ($state) {
                                    'RBFA' => 'success',
                                    'VAL' => 'warning',
                                    'LBFA' => 'info',
                                    'TPV' => 'success',
                                    'AFT' => 'danger',
                                    'VHL' => 'info',
                                    'LFH' => 'warning',
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
                                ->label(__('prospects::resource.fields.contact_person')),
                            TextEntry::make('channel')
                                ->label(__('prospects::resource.fields.channel')),
                            TextEntry::make('vat_number')
                                ->label(__('prospects::resource.fields.vat_number')),
                            TextEntry::make('cafca_relation_id')
                                ->label(__('prospects::resource.fields.cafca_id')),
                        ]),
                    ]),
                Section::make(__('prospects::resource.sections.marketing_targets'))
                    ->components([
                        RepeatableEntry::make('locations')
                            ->label(__('prospects::resource.fields.locations'))
                            ->components([
                                Grid::make(3)->components([
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
                                        ->columnSpan(2),
                                    TextEntry::make('phone')
                                        ->label(__('prospects::resource.fields.phone'))
                                        ->icon('heroicon-m-phone'),
                                    TextEntry::make('address')
                                        ->label(__('prospects::resource.fields.address'))
                                        ->icon('heroicon-m-map-pin')
                                        ->columnSpanFull(),
                                ]),
                            ])
                            ->columns(1),
                    ]),
            ]);
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
                        'VAL' => 'warning',
                        'LBFA' => 'info',
                        'TPV' => 'success',
                        'AFT' => 'danger',
                        'VHL' => 'info',
                        'LFH' => 'warning',
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
                IconColumn::make('has_email')
                    ->label(__('prospects::resource.fields.email'))
                    ->boolean()
                    ->state(function (Prospect $record): bool {
                        return $record->locations()->whereNotNull('email')->where('email', '!=', '')->exists();
                    }),
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
                Filter::make('type_filter')
                    ->form([
                        Select::make('type')
                            ->label(__('prospects::resource.fields.type_sport'))
                            ->options([
                                'football_club' => __('prospects::resource.options.sport_types.football_club'),
                                'athletics_club' => __('prospects::resource.options.sport_types.athletics_club'),
                                'tennis_padel_club' => __('prospects::resource.options.sport_types.tennis_padel_club'),
                                'hockey_club' => __('prospects::resource.options.sport_types.hockey_club'),
                            ])
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['type'],
                                fn(Builder $query, $type): Builder => $query->where('type', $type),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['type'] ?? null) {
                            $label = match ($data['type']) {
                                'football_club' => __('prospects::resource.options.sport_types.football_club'),
                                'athletics_club' => __('prospects::resource.options.sport_types.athletics_club'),
                                'tennis_padel_club' => __('prospects::resource.options.sport_types.tennis_padel_club'),
                                'hockey_club' => __('prospects::resource.options.sport_types.hockey_club'),
                                default => $data['type'],
                            };
                            $indicators['type'] = __('prospects::resource.fields.type_sport') . ': ' . $label;
                        }
                        return $indicators;
                    }),
                Filter::make('federation_filter')
                    ->form([
                        Select::make('federation')
                            ->label(__('prospects::resource.fields.federation'))
                            ->options([
                                'RBFA' => 'RBFA (Voetbal)',
                                'VAL' => 'VAL (Atletiek NL)',
                                'LBFA' => 'LBFA (Atletiek FR)',
                                'TPV' => 'Tennis & Padel VL.',
                                'AFT' => 'AFT (Tennis FR)',
                                'VHL' => 'VHL (Hockey NL)',
                                'LFH' => 'LFH (Hockey FR)',
                                'ARBH-KBHB' => 'ARBH (Koninklijk)',
                            ])
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['federation'],
                                fn(Builder $query, $fed): Builder => $query->where('federation', $fed),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['federation'] ?? null) {
                            $indicators['federation'] = __('prospects::resource.fields.federation') . ': ' . $data['federation'];
                        }
                        return $indicators;
                    }),
            ])
            ->filtersLayout(FiltersLayout::Dropdown)
            ->filtersFormColumns(1)
            ->filtersApplyAction(
                fn(Action $action) => $action
                    ->label(__('prospects::resource.actions.apply_filters'))
                    ->close(),
            )
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('execute_campaign')
                        ->label(__('prospects::resource.actions.execute_campaign.label'))
                        ->icon('heroicon-o-rocket-launch')
                        ->color('primary')
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

                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->title(__('prospects::resource.notifications.no_prospects_selected.title'))
                                    ->warning()
                                    ->send();
                                return;
                            }

                            // Verificar si al menos uno tiene email (Verificamos en las locaciones)
                            $hasEmails = $records->contains(fn ($p) => $p->locations()->whereNotNull('email')->where('email', '!=', '')->exists());

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

                            \Illuminate\Support\Facades\Log::info("Dispatching Prospect Mailing Job", [
                                'user_id' => $userId,
                                'prospect_count' => $records->count(),
                                'description' => $data['description'],
                            ]);

                            ExecuteMailingCampaignJob::dispatch(
                                $records->pluck('id')->toArray(), 
                                $data['template_id'], 
                                $userId,
                                $data['description']
                            );

                            Notification::make()
                                ->title(__('prospects::resource.notifications.campaign_started.title'))
                                ->success()
                                ->body(__('prospects::resource.notifications.campaign_started.body'))
                                ->send();

                            // Asegurar la limpieza inmediata para el FAB
                            $livewire->deselectAllTableRecords();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProspects::route('/'),
        ];
    }
}
