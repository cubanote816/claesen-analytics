<?php

namespace App\Filament\Resources\Prospects;

use App\Filament\Resources\Prospects\Pages\ManageProspects;
use App\Models\Prospect;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
use Illuminate\Database\Eloquent\Builder;

class ProspectResource extends Resource
{
    protected static ?string $model = Prospect::class;

    protected static ?string $modelLabel = 'Prospect';
    protected static ?string $pluralModelLabel = 'Prospecten';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Club Informatie')
                    ->components([
                        TextInput::make('name')
                            ->label('Naam van de Club')
                            ->required(),
                        Select::make('region')
                            ->label('Regio')
                            ->options([
                                'Limburg' => 'Limburg',
                                'Antwerpen' => 'Antwerpen',
                            ]),
                        Select::make('league')
                            ->label('Liga')
                            ->options(Prospect::query()->whereNotNull('league')->distinct()->pluck('league', 'league')->toArray()),
                        TextInput::make('league_id')
                            ->label('Liga ID'),
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
                            TextEntry::make('region')
                                ->label('Regio'),
                            TextEntry::make('league')
                                ->label('Liga'),
                            TextEntry::make('league_id')
                                ->label('Liga ID'),
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
                                        }),
                                    TextEntry::make('email')
                                        ->label('E-mail')
                                        ->icon('heroicon-m-envelope'),
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
                TextColumn::make('region')
                    ->label('Regio')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('league')
                    ->label('Liga')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('league_id')
                    ->label('Liga ID')
                    ->sortable()
                    ->searchable()
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
                    ),
                Filter::make('region_league_filter')
                    ->form([
                        Select::make('region')
                            ->label('Regio')
                            ->options([
                                'Limburg' => 'Limburg',
                                'Antwerpen' => 'Antwerpen',
                            ])
                            ->live(),
                        Select::make('league')
                            ->label('Liga')
                            ->options(function (Get $get) {
                                $region = $get('region');
                                if ($region) {
                                    return Prospect::query()
                                        ->where('region', $region)
                                        ->whereNotNull('league')
                                        ->distinct()
                                        ->pluck('league', 'league')
                                        ->toArray();
                                }
                                return Prospect::query()
                                    ->whereNotNull('league')
                                    ->distinct()
                                    ->pluck('league', 'league')
                                    ->toArray();
                            }),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['region'],
                                fn(Builder $query, $region): Builder => $query->where('region', $region),
                            )
                            ->when(
                                $data['league'],
                                fn(Builder $query, $league): Builder => $query->where('league', $league),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['region'] ?? null) {
                            $indicators['region'] = 'Regio: ' . $data['region'];
                        }
                        if ($data['league'] ?? null) {
                            $indicators['league'] = 'Liga: ' . $data['league'];
                        }
                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
