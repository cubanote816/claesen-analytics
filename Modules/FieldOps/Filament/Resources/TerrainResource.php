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
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Modules\FieldOps\Filament\Resources\Terrains\Pages\CreateTerrain;
use Modules\FieldOps\Filament\Resources\Terrains\Pages\EditTerrain;
use Modules\FieldOps\Filament\Resources\Terrains\Pages\ListTerrains;
use Modules\FieldOps\Filament\Resources\Terrains\Pages\ViewTerrain;
use Modules\FieldOps\Filament\Resources\Terrains\RelationManagers\ElectricalBoardsRelationManager;
use Modules\FieldOps\Filament\Resources\Terrains\RelationManagers\StructuresRelationManager;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Models\TerrainType;
use Filament\Schemas\Components\Utilities\Set;

class TerrainResource extends Resource
{
    protected static ?string $model = Terrain::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.field_operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('fieldops::resource.terrains.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.terrains.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.terrains.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        $locationDefaults = static::resolveLocationDefaults();
        $terrainPinVariants = static::resolveTerrainPinVariants();

        return $schema
            ->columns(1)
            ->components([
            Section::make()
                ->columnSpanFull()
                ->extraAttributes(['class' => 'relative z-30'])
                ->schema([
                // Single field in the admin's current locale (app()->getLocale(),
                // set per-request by SetPanelLocale) — HasAiTranslations
                // auto-translates to the other 3 canonical locales on save.
                TextInput::make('name')
                    ->label(__('fieldops::resource.terrains.fields.name'))
                    ->required(),
                Select::make('complex_id')
                    ->label(__('fieldops::resource.terrains.fields.complex'))
                    ->options(Complex::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state): void {
                        $location = static::resolveLocationDefaults($state ? (int) $state : null);
                        $set('map_center_lat', $location['lat']);
                        $set('map_center_lng', $location['lng']);
                        $set('lat', $location['lat']);
                        $set('lng', $location['lng']);
                    })
                    ->default(fn () => request()->integer('complex_id') ?: null)
                    ->required(),
                Select::make('terrain_type_id')
                    ->label(__('fieldops::resource.terrains.fields.terrain_type'))
                    ->options(TerrainType::all()->mapWithKeys(fn ($t) => [
                        $t->id => $t->getTranslation('type', app()->getLocale(), false)
                            ?: $t->getTranslation('type', 'nl', false),
                    ]))
                    ->searchable()
                    ->extraFieldWrapperAttributes(['class' => 'relative z-50'])
                    ->live()
                    ->afterStateHydrated(function (Set $set, $state): void {
                        $set('terrain_pin_variant', static::resolveTerrainPinVariant($state ? (int) $state : null));
                    })
                    ->afterStateUpdated(function (Set $set, $state): void {
                        $set('terrain_pin_variant', static::resolveTerrainPinVariant($state ? (int) $state : null));
                    })
                    ->nullable(),
            ])->columns(3),
            Section::make()
                ->columnSpanFull()
                ->extraAttributes(['class' => 'relative z-10'])
                ->schema([
                Hidden::make('lat')
                    ->default($locationDefaults['lat']),
                Hidden::make('lng')
                    ->default($locationDefaults['lng']),
                Hidden::make('map_center_lat')
                    ->dehydrated(false)
                    ->default($locationDefaults['lat']),
                Hidden::make('map_center_lng')
                    ->dehydrated(false)
                    ->default($locationDefaults['lng']),
                Hidden::make('terrain_pin_variant')
                    ->dehydrated(false)
                    ->default(static::resolveTerrainPinVariant(null)),
                ViewField::make('location_map')
                    ->hiddenLabel()
                    ->dehydrated(false)
                    ->view('fieldops::filament.forms.terrain-location-picker')
                    ->viewData([
                        'complexLabel' => $locationDefaults['complex_label'],
                        'defaultLat' => $locationDefaults['lat'],
                        'defaultLng' => $locationDefaults['lng'],
                        'defaultZoom' => $locationDefaults['zoom'],
                        'latInputId' => 'form.lat',
                        'lngInputId' => 'form.lng',
                        'centerLatInputId' => 'form.map_center_lat',
                        'centerLngInputId' => 'form.map_center_lng',
                        'variantInputId' => 'form.terrain_pin_variant',
                        'pinVariants' => $terrainPinVariants,
                        'defaultPinVariant' => static::resolveTerrainPinVariant(null),
                    ])
                    ->columnSpanFull(),
            ])->columns(1),
            Section::make(__('fieldops::resource.media.section_label'))
                ->columnSpanFull()
                ->schema([
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
                ->state(fn (Terrain $record) => [
                    'eyebrow' => static::getModelLabel(),
                    'name' => $record->getTranslation('name', app()->getLocale(), false)
                        ?: $record->getTranslation('name', 'nl', false)
                        ?: '#'.$record->id,
                    'facts' => array_values(array_filter([
                        [
                            'label' => __('fieldops::resource.terrains.fields.terrain_type'),
                            'value' => $record->terrainType?->getTranslation('type', app()->getLocale(), false)
                                ?: $record->terrainType?->getTranslation('type', 'nl', false),
                            'placeholder' => '—',
                        ],
                        $record->complex ? [
                            'label' => __('fieldops::resource.complexes.model_label'),
                            'value' => $record->complex->name,
                            'placeholder' => '—',
                            'url' => ComplexResource::getUrl('view', ['record' => $record->complex]),
                        ] : null,
                    ])),
                ])
                ->view('fieldops::filament.infolists.profile-header')
                ->columnSpanFull(),

            ViewEntry::make('map_panel')
                ->hiddenLabel()
                ->state(fn (Terrain $record) => static::buildMapPanelState($record))
                ->view('fieldops::filament.infolists.map-panel')
                ->columnSpanFull(),

            Section::make(__('fieldops::resource.media.section_label'))
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
    protected static function buildMapPanelState(Terrain $record): array
    {
        $record->loadMissing(['terrainType', 'structures.structureType', 'electricalBoards.electricalBoardType']);

        $structureMarkers = $record->structures
            ->map(fn ($structure) => [
                'type' => 'structure',
                'label' => $structure->structureType?->getTranslation('name', app()->getLocale(), false)
                    ?: $structure->structureType?->getTranslation('name', 'nl', false)
                    ?: '#'.$structure->id,
                'description' => $structure->height ? __('fieldops::resource.structures.fields.height').': '.$structure->height.'m' : null,
                'lat' => $structure->lat,
                'lng' => $structure->lng,
                'hasCoordinates' => static::hasCoordinates($structure),
                'url' => StructureResource::getUrl('view', ['record' => $structure]),
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

        $terrainMarker = static::hasCoordinates($record)
            ? collect([[
                'type' => 'terrain',
                'label' => $record->getTranslation('name', app()->getLocale(), false)
                    ?: $record->getTranslation('name', 'nl', false)
                    ?: '#'.$record->id,
                'description' => $record->terrainType?->getTranslation('type', app()->getLocale(), false)
                    ?: $record->terrainType?->getTranslation('type', 'nl', false),
                'terrainTypeCode' => $record->terrainType?->code,
                'terrainTypeColor' => $record->terrainType?->pin_color,
                'lat' => $record->lat,
                'lng' => $record->lng,
                'hasCoordinates' => true,
                'url' => null,
            ]])
            : collect();

        $items = $terrainMarker
            ->concat($structureMarkers)
            ->concat($boardMarkers)
            ->values()
            ->all();

        return [
            'title' => 'Map overview',
            'subtitle' => 'Desktop map overview for this terrain, its structures, and its electrical boards.',
            'emptyTitle' => 'No coordinates available yet',
            'emptyDescription' => 'No terrain, structure, or electrical board coordinates are available yet.',
            'summary' => [
                ['value' => $record->structures->count(), 'label' => __('fieldops::resource.structures.plural_label')],
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

    /**
     * @return array{lat: float, lng: float, zoom: int, complex_label: ?string}
     */
    protected static function resolveLocationDefaults(?int $complexId = null): array
    {
        $fallbackLat = 51.1635;
        $fallbackLng = 5.1640;
        $fallbackZoom = 16;

        $complex = $complexId ? Complex::query()->find($complexId) : null;

        if ($complex && static::hasCoordinates($complex)) {
            return [
                'lat' => (float) $complex->lat,
                'lng' => (float) $complex->lng,
                'zoom' => 17,
                'complex_label' => $complex->name,
            ];
        }

        return [
            'lat' => $fallbackLat,
            'lng' => $fallbackLng,
            'zoom' => $fallbackZoom,
            'complex_label' => $complex?->name,
        ];
    }

    /**
     * @return array<string, array{label: string, initial: string, color: string, text: string}>
     */
    /**
     * `pin_color` (CLA-256) is the single source of truth for terrain pin color — shared
     * with the /terrain-types API payload consumed by the Claesen-Sport frontend, so both
     * apps render the same color per terrain type. Previously this cycled a hardcoded
     * palette by row index, which reshuffled every color whenever a type was inserted or
     * reordered.
     */
    protected static function resolveTerrainPinVariants(): array
    {
        $fallbackColor = '#00aeef';

        return TerrainType::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (TerrainType $type) use ($fallbackColor): array {
                $label = $type->getTranslation('type', app()->getLocale(), false)
                    ?: $type->getTranslation('type', 'nl', false)
                    ?: '#'.$type->id;

                return [
                    (string) $type->id => [
                        'label' => $label,
                        'initial' => Str::of($label)->trim()->substr(0, 1)->upper()->value() ?: 'T',
                        'code' => $type->code,
                        'color' => $type->pin_color ?: $fallbackColor,
                        'text' => '#ffffff',
                    ],
                ];
            })
            ->prepend([
                'label' => __('fieldops::resource.terrains.fields.terrain_type'),
                'initial' => 'T',
                'color' => '#e6007e',
                'text' => '#ffffff',
            ], 'generic')
            ->all();
    }

    protected static function resolveTerrainPinVariant(?int $terrainTypeId): string
    {
        if (! $terrainTypeId) {
            return 'generic';
        }

        $exists = TerrainType::query()->whereKey($terrainTypeId)->exists();

        return $exists ? (string) $terrainTypeId : 'generic';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('fieldops::resource.terrains.fields.name'))
                    ->getStateUsing(fn ($record) =>
                        $record->getTranslation('name', app()->getLocale(), false)
                        ?: $record->getTranslation('name', 'nl', false)
                    )
                    ->searchable(),
                TextColumn::make('complex.name')
                    ->label(__('fieldops::resource.terrains.fields.complex'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('terrainType.type')
                    ->label(__('fieldops::resource.terrains.fields.terrain_type'))
                    ->getStateUsing(fn ($record) =>
                        $record->terrainType?->getTranslation('type', app()->getLocale(), false)
                        ?: $record->terrainType?->getTranslation('type', 'nl', false)
                    )
                    ->badge()
                    ->color('info'),
                TextColumn::make('structures_count')
                    ->label(__('fieldops::resource.terrains.fields.structures_count'))
                    ->counts('structures')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('complex_id')
                    ->label(__('fieldops::resource.terrains.fields.complex'))
                    ->options(Complex::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['complex', 'terrainType'])
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getRelations(): array
    {
        return [
            StructuresRelationManager::class,
            // Reused from StructureResource — same relationship name ("electricalBoards"),
            // same generic attach/detach behaviour, no Structure-specific logic inside.
            ElectricalBoardsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTerrains::route('/'),
            'create' => CreateTerrain::route('/create'),
            'view'   => ViewTerrain::route('/{record}'),
            'edit'   => EditTerrain::route('/{record}/edit'),
        ];
    }
}
