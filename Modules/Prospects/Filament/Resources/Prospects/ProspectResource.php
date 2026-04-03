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

    protected static ?string $modelLabel = 'Prospect';
    protected static ?string $pluralModelLabel = 'Prospecten';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return 'Groei & Acquisitie';
    }

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Club Informatie')
                    ->components([
                        TextInput::make('name')
                            ->label('Naam van de Club')
                            ->required(),
                        Select::make('region_id')
                            ->label('Regio')
                            ->relationship('region', 'name')
                            ->required(),
                        Select::make('federation')
                            ->label('Federatie')
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
                            ->label('Taal')
                            ->options([
                                'nl' => 'Nederlands',
                                'fr' => 'Frans',
                                'en' => 'Engels',
                            ]),
                        TextInput::make('contact_person')
                            ->label('Secretaris / Contactpersoon'),
                        TextInput::make('channel')
                            ->label('Kanaal'),
                        TextInput::make('website')
                            ->label('Website')
                            ->url(),
                        TextInput::make('vat_number')
                            ->label('BTW Nummer'),
                        TextInput::make('cafca_relation_id')
                            ->label('CAFCA Relatie ID'),
                    ]),
                Section::make('Marketing Doelwitten')
                    ->components([
                        Repeater::make('locations')
                            ->label('Locaties')
                            ->relationship()
                            ->components([
                                Select::make('location_type')
                                    ->label('Type Locatie')
                                    ->options([
                                        'headquarters' => 'Hoofdkantoor',
                                        'stadium' => 'Stadion',
                                        'venue_name' => 'Locatie Naam',
                                    ])
                                    ->required(),
                                TextInput::make('email')
                                    ->label('E-mail')
                                    ->email(),
                                TextInput::make('phone')
                                    ->label('Telefoonnummer'),
                                Textarea::make('address')
                                    ->label('Adres')
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
                Section::make('Club Informatie')
                    ->components([
                        ImageEntry::make('logo_url')
                            ->label('Logo')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->height(100),
                        Grid::make(2)->components([
                            TextEntry::make('name')
                                ->label('Naam van de Club')
                                ->weight('bold')
                                ->size('lg'),
                            TextEntry::make('website')
                                ->label('Website')
                                ->url(fn($record) => $record->website, true),
                            TextEntry::make('region.name')
                                ->label('Regio'),
                            TextEntry::make('federation')
                                ->label('Federatie')
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
                                ->label('Taal')
                                ->formatStateUsing(fn(string $state): string => match ($state) {
                                    'nl' => 'Nederlands',
                                    'fr' => 'Frans',
                                    'en' => 'Engels',
                                    default => $state,
                                }),
                            TextEntry::make('contact_person')
                                ->label('Secretaris'),
                            TextEntry::make('channel')
                                ->label('Kanaal'),
                            TextEntry::make('vat_number')
                                ->label('BTW Nummer'),
                            TextEntry::make('cafca_relation_id')
                                ->label('CAFCA Relatie ID'),
                        ]),
                    ]),
                Section::make('Marketing Doelwitten')
                    ->components([
                        RepeatableEntry::make('locations')
                            ->label('Locaties')
                            ->components([
                                Grid::make(3)->components([
                                    TextEntry::make('location_type')
                                        ->label('Type Locatie')
                                        ->badge()
                                        ->color('info')
                                        ->formatStateUsing(fn(string $state): string => match ($state) {
                                            'headquarters' => 'Hoofdkantoor',
                                            'stadium' => 'Stadion',
                                            'venue_name' => 'Locatie Naam',
                                            default => $state,
                                        })
                                        ->columnSpanFull(),
                                    TextEntry::make('email')
                                        ->label('E-mail')
                                        ->icon('heroicon-m-envelope')
                                        ->columnSpan(2),
                                    TextEntry::make('phone')
                                        ->label('Telefoonnummer')
                                        ->icon('heroicon-m-phone'),
                                    TextEntry::make('address')
                                        ->label('Adres')
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
                    ->label('Logo')
                    ->circular(),
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(),
                TextColumn::make('region.name')
                    ->label('Regio')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('federation')
                    ->label('Federatie')
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
                    ->label('Taal')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('channel')
                    ->label('Kanaal')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('website')
                    ->label('Website'),
                IconColumn::make('has_email')
                    ->label('E-mail')
                    ->boolean()
                    ->state(function (Prospect $record): bool {
                        return $record->locations()->whereNotNull('email')->where('email', '!=', '')->exists();
                    }),
                TextColumn::make('locations_count')
                    ->counts('locations')
                    ->label('Aantal Locaties'),
            ])
            ->filters([
                TernaryFilter::make('has_email')
                    ->label('Heeft E-mailadres')
                    ->queries(
                        true: fn(Builder $query) => $query->whereHas('locations', fn($q) => $q->whereNotNull('email')->where('email', '!=', '')),
                        false: fn(Builder $query) => $query->whereDoesntHave('locations', fn($q) => $q->whereNotNull('email')->where('email', '!=', '')),
                        blank: fn(Builder $query) => $query,
                    )
                    ->native(false),
                Filter::make('region_filter')
                    ->form([
                        Select::make('region_id')
                            ->label('Regio')
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
                            $indicators['region'] = 'Regio: ' . $regionName;
                        }
                        return $indicators;
                    }),
                Filter::make('type_filter')
                    ->form([
                        Select::make('type')
                            ->label('Type Sport')
                            ->options([
                                'football_club' => 'Voetbal',
                                'athletics_club' => 'Atletiek',
                                'tennis_padel_club' => 'Tennis & Padel',
                                'hockey_club' => 'Hockey',
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
                                'football_club' => 'Voetbal',
                                'athletics_club' => 'Atletiek',
                                'tennis_padel_club' => 'Tennis & Padel',
                                'hockey_club' => 'Hockey',
                                default => $data['type'],
                            };
                            $indicators['type'] = 'Sport: ' . $label;
                        }
                        return $indicators;
                    }),
                Filter::make('federation_filter')
                    ->form([
                        Select::make('federation')
                            ->label('Federatie')
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
                            $indicators['federation'] = 'Federatie: ' . $data['federation'];
                        }
                        return $indicators;
                    }),
            ])
            ->filtersLayout(FiltersLayout::Dropdown)
            ->filtersFormColumns(1)
            ->filtersApplyAction(
                fn(Action $action) => $action
                    ->label('Apply filters')
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
                        ->label('Start Mailing Campagne')
                        ->icon('heroicon-o-rocket-launch')
                        ->color('primary')
                        ->form([
                            Select::make('template_id')
                                ->label('Kies E-mail Sjabloon')
                                ->options(\Modules\Mailing\Models\EmailTemplate::query()->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            ExecuteMailingCampaignJob::dispatch($records->pluck('id')->toArray(), $data['template_id']);
                            Notification::make()
                                ->title('Campagne Gestart')
                                ->success()
                                ->body('De e-mails worden op de achtergrond verzonden met het gekozen sjabloon.')
                                ->send();
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
