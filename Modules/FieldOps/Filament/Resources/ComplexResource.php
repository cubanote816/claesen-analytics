<?php

namespace Modules\FieldOps\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\FieldOps\Filament\Resources\Complexes\Pages\EditComplex;
use Modules\FieldOps\Filament\Resources\Complexes\Pages\ListComplexes;
use Modules\FieldOps\Filament\Resources\Complexes\Pages\ViewComplex;
use Modules\FieldOps\Filament\Resources\Complexes\RelationManagers\TerrainsRelationManager;
use Modules\FieldOps\Filament\Resources\Structures\RelationManagers\ElectricalBoardsRelationManager;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoClient;

class ComplexResource extends Resource
{
    protected static ?string $model = Complex::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.field_operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('fieldops::resource.complexes.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.complexes.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.complexes.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label(__('fieldops::resource.complexes.fields.name'))
                    ->required()
                    ->maxLength(255),
                Select::make('client_id')
                    ->label(__('fieldops::resource.complexes.fields.client'))
                    ->options(FoClient::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                TextInput::make('street')
                    ->label(__('fieldops::resource.complexes.fields.street'))
                    ->maxLength(255),
                TextInput::make('city')
                    ->label(__('fieldops::resource.complexes.fields.city'))
                    ->maxLength(255),
                TextInput::make('zipcode')
                    ->label(__('fieldops::resource.complexes.fields.zipcode'))
                    ->maxLength(20),
                TextInput::make('lat')
                    ->label(__('fieldops::resource.complexes.fields.lat'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('lng')
                    ->label(__('fieldops::resource.complexes.fields.lng'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('zoom')
                    ->label(__('fieldops::resource.complexes.fields.zoom'))
                    ->numeric()
                    ->default(17.0)
                    ->nullable(),
            ])->columns(2),
            Section::make(__('fieldops::resource.media.section_label'))->schema([
                SpatieMediaLibraryFileUpload::make('photos')
                    ->label(__('fieldops::resource.media.photos'))
                    ->collection('photos')
                    ->image()
                    ->multiple()
                    ->maxSize(10240)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                SpatieMediaLibraryFileUpload::make('documents')
                    ->label(__('fieldops::resource.media.documents'))
                    ->collection('documents')
                    ->multiple()
                    ->maxSize(20480)
                    ->acceptedFileTypes(['application/pdf']),
            ])->collapsible()->collapsed(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('profile_header')
                ->hiddenLabel()
                ->state(fn (Complex $record) => [
                    'eyebrow' => static::getModelLabel(),
                    'name' => $record->name,
                    'chips' => [
                        $record->client ? [
                            'label' => $record->client->name,
                            'color' => 'info',
                            'url' => \Modules\FieldOps\Filament\Resources\FoClientResource::getUrl('view', ['record' => $record->client]),
                        ] : ['label' => __('fieldops::resource.complexes.fields.client').': —', 'color' => 'warning'],
                        ['label' => 'zoom '.$record->zoom, 'color' => 'gray'],
                    ],
                    'stat' => [
                        'value' => $record->terrains()->count(),
                        'label' => __('fieldops::resource.terrains.plural_label'),
                    ],
                    'actions' => [
                        [
                            'label' => __('fieldops::resource.terrains.actions.create'),
                            'url' => \Modules\FieldOps\Filament\Resources\TerrainResource::getUrl('create', [
                                'complex_id' => $record->getKey(),
                            ]),
                        ],
                    ],
                    'meta' => [
                        [
                            'label' => __('fieldops::resource.complexes.fields.address'),
                            'value' => collect([$record->street, $record->zipcode, $record->city])->filter()->implode(', ') ?: null,
                            'placeholder' => '—',
                            'icon' => 'heroicon-o-map-pin',
                        ],
                        [
                            'type' => 'map',
                            'label' => __('fieldops::resource.complexes.fields.map'),
                            'placeholder' => __('fieldops::resource.complexes.no_coordinates'),
                            // Google Maps for now — a page of our own showing the complex
                            // with all its terrain/structure pins is a bigger follow-up.
                            'url' => ($record->lat !== null && $record->lng !== null)
                                ? "https://www.google.com/maps/search/?api=1&query={$record->lat},{$record->lng}"
                                : null,
                            'newTab' => true,
                        ],
                    ],
                ])
                ->view('fieldops::filament.infolists.profile-header')
                ->columnSpanFull(),

            ViewEntry::make('map_panel')
                ->hiddenLabel()
                ->state(fn (Complex $record) => static::buildMapPanelState($record))
                ->view('fieldops::filament.infolists.map-panel')
                ->columnSpanFull(),

            Section::make(__('fieldops::resource.media.section_label'))
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('photos')
                        ->label(__('fieldops::resource.media.photos'))
                        ->state(fn ($record) => $record->getMedia('photos'))
                        ->default(fn () => collect())
                        ->view('fieldops::filament.infolists.media-gallery'),
                    TextEntry::make('documents_count')
                        ->label(__('fieldops::resource.media.documents'))
                        ->state(fn ($record) => (string) $record->getMedia('documents')->count()),
                ])
                ->collapsible()
                ->collapsed(fn ($record) => $record->getMedia('photos')->isEmpty() && $record->getMedia('documents')->isEmpty()),
        ]);
    }

    /**
     * @return array{title: string, subtitle: string, emptyTitle: string, emptyDescription: string, summary: array<int, array{label: string, value: int|string}>, markers: array<int, array<string, mixed>>}
     */
    protected static function buildMapPanelState(Complex $record): array
    {
        $record->loadMissing(['terrains.terrainType', 'electricalBoards.electricalBoardType']);

        $terrainMarkers = $record->terrains
            ->map(fn ($terrain) => [
                'type' => 'terrain',
                'label' => $terrain->getTranslation('name', app()->getLocale(), false)
                    ?: $terrain->getTranslation('name', 'nl', false)
                    ?: '#'.$terrain->id,
                'description' => $terrain->terrainType?->getTranslation('type', app()->getLocale(), false)
                    ?: $terrain->terrainType?->getTranslation('type', 'nl', false),
                'lat' => $terrain->lat,
                'lng' => $terrain->lng,
                'hasCoordinates' => static::hasCoordinates($terrain),
                'url' => TerrainResource::getUrl('view', ['record' => $terrain]),
            ]);

        $boardMarkers = $record->electricalBoards
            ->map(fn ($board) => [
                'type' => 'electrical-board',
                'label' => $board->electricalBoardType?->getTranslation('name', app()->getLocale(), false)
                    ?: $board->electricalBoardType?->getTranslation('name', 'nl', false)
                    ?: '#'.$board->id,
                'description' => $board->getTranslation('location_description', app()->getLocale(), false)
                    ?: $board->getTranslation('location_description', 'nl', false),
                'lat' => $board->lat,
                'lng' => $board->lng,
                'hasCoordinates' => static::hasCoordinates($board),
                'url' => ElectricalBoardResource::getUrl('view', ['record' => $board]),
            ]);

        $complexMarker = static::hasCoordinates($record)
            ? collect([[
                'type' => 'complex',
                'label' => $record->name,
                'description' => collect([$record->street, $record->zipcode, $record->city])->filter()->implode(', '),
                'lat' => $record->lat,
                'lng' => $record->lng,
                'hasCoordinates' => true,
                'url' => null,
            ]])
            : collect();

        $items = $complexMarker
            ->concat($terrainMarkers)
            ->concat($boardMarkers)
            ->values()
            ->all();

        return [
            'title' => __('fieldops::resource.complexes.fields.map'),
            'subtitle' => 'Desktop map overview for this complex and its related FieldOps assets.',
            'emptyTitle' => __('fieldops::resource.complexes.no_coordinates'),
            'emptyDescription' => 'No complex, terrain, or electrical board coordinates are available yet.',
            'summary' => [
                ['value' => $record->terrains->count(), 'label' => __('fieldops::resource.terrains.plural_label')],
                ['value' => $record->electricalBoards->count(), 'label' => __('fieldops::resource.electrical_boards.plural_label')],
            ],
            'items' => $items,
            'markers' => array_values(array_filter($items, fn ($item) => ! empty($item['hasCoordinates']))),
        ];
    }

    protected static function hasCoordinates($record): bool
    {
        return is_numeric($record->lat ?? null) && is_numeric($record->lng ?? null);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('fieldops::resource.complexes.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')
                    ->label(__('fieldops::resource.complexes.fields.city'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label(__('fieldops::resource.complexes.fields.client'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('terrains_count')
                    ->label(__('fieldops::resource.complexes.fields.terrains_count'))
                    ->counts('terrains')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TerrainsRelationManager::class,
            // Reused from StructureResource — same relationship name ("electricalBoards"),
            // same generic attach/detach behaviour. First real attach/detach UI for this
            // pivot from the Complex side (Structure/Terrain already had it, Complex didn't).
            ElectricalBoardsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplexes::route('/'),
            'view'  => ViewComplex::route('/{record}'),
            'edit'  => EditComplex::route('/{record}/edit'),
        ];
    }
}
