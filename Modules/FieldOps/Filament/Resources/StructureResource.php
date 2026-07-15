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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Toggle;
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
use Illuminate\Support\Arr;
use Modules\FieldOps\Filament\Resources\Structures\Pages\CreateStructure;
use Modules\FieldOps\Filament\Resources\Structures\Pages\EditStructure;
use Modules\FieldOps\Filament\Resources\Structures\Pages\ListStructures;
use Modules\FieldOps\Filament\Resources\Structures\Pages\ViewStructure;
use Modules\FieldOps\Filament\Resources\Structures\RelationManagers\ElectricalBoardsRelationManager;
use Modules\FieldOps\Filament\Resources\Structures\RelationManagers\LuminaireFramesRelationManager;
use Modules\FieldOps\Filament\Resources\Structures\RelationManagers\TerrainsRelationManager;
use Modules\FieldOps\Models\AccessType;
use Modules\FieldOps\Models\SafetyType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\StructureType;
use Modules\FieldOps\Models\Terrain;

class StructureResource extends Resource
{
    protected static ?string $model = Structure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBoltSlash;

    // Structure has no "name" column — the closest thing to an identifying label
    // is its type + id, same format already used for Select options elsewhere.
    // hasRecordTitle() must also be overridden: Filament only calls getRecordTitle()
    // when it's true, and its default implementation only checks $recordTitleAttribute
    // (which doesn't apply here, since the title isn't a single plain column).
    public static function hasRecordTitle(): bool
    {
        return true;
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        if (! $record instanceof Structure) {
            return static::getModelLabel();
        }

        $typeName = $record->structureType?->getTranslation('name', app()->getLocale(), false)
            ?: $record->structureType?->getTranslation('name', 'nl', false);

        return '#'.$record->id.($typeName ? " — {$typeName}" : '');
    }

    protected static ?int $navigationSort = 4;

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
        return __('fieldops::resource.structures.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.structures.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.structures.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        $terrainIds = Arr::wrap(request()->input('terrain_ids', []));
        $locationDefaults = static::resolveLocationDefaults($terrainIds);

        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->visible(fn ($livewire): bool => $livewire instanceof CreateStructure && filled($livewire->proximityMatch))
                ->schema([
                    ViewField::make('proximity_warning')
                        ->hiddenLabel()
                        ->dehydrated(false)
                        ->view('fieldops::filament.forms.structure-proximity-warning')
                        ->viewData(fn ($livewire): array => [
                            'proximityMatch' => $livewire->proximityMatch,
                        ]),
                ]),
            Section::make()
                ->columnSpanFull()
                ->schema([
                    Select::make('structure_type_id')
                        ->label(__('fieldops::resource.structures.fields.structure_type'))
                        ->options(StructureType::all()->mapWithKeys(fn ($t) => [
                            $t->id => $t->getTranslation('name', app()->getLocale(), false)
                                ?: $t->getTranslation('name', 'nl', false),
                        ]))
                        ->searchable()
                        ->required(),
                    Select::make('terrain_ids')
                        ->label(__('fieldops::resource.terrains.plural_label'))
                        ->multiple()
                        ->searchable()
                        ->visible(fn (string $operation): bool => $operation === 'create')
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->default(fn () => Arr::wrap(request()->input('terrain_ids', [])))
                        ->helperText(__('fieldops::resource.structures.validation.terrain_helper'))
                        ->options(Terrain::query()
                            ->orderBy('name')
                            ->limit(100)
                            ->get()
                            ->mapWithKeys(fn (Terrain $terrain) => [
                                $terrain->id => $terrain->getTranslation('name', app()->getLocale(), false)
                                    ?: $terrain->getTranslation('name', 'nl', false),
                            ])
                            ->all()),
                    TextInput::make('height')
                        ->label(__('fieldops::resource.structures.fields.height'))
                        ->numeric()
                        ->nullable(),
                    Select::make('access_type_id')
                        ->label(__('fieldops::resource.structures.fields.access_type'))
                        ->options(AccessType::all()->mapWithKeys(fn ($t) => [
                            $t->id => $t->getTranslation('name', app()->getLocale(), false)
                                ?: $t->getTranslation('name', 'nl', false),
                        ]))
                        ->searchable()
                        ->nullable(),
                    Toggle::make('access_active')
                        ->label(__('fieldops::resource.structures.fields.access_active'))
                        ->default(false),
                    Select::make('safety_type_id')
                        ->label(__('fieldops::resource.structures.fields.safety_type'))
                        ->options(SafetyType::all()->mapWithKeys(fn ($t) => [
                            $t->id => $t->getTranslation('name', app()->getLocale(), false)
                                ?: $t->getTranslation('name', 'nl', false),
                        ]))
                        ->searchable()
                        ->nullable(),
                    Toggle::make('safety_certified')
                        ->label(__('fieldops::resource.structures.fields.safety_certified'))
                        ->default(false),
                    TextInput::make('cafca_material_id')
                        ->label(__('fieldops::resource.structures.fields.cafca_material_id'))
                        ->nullable(),
                ])->columns(4),
            Section::make()
                ->columnSpanFull()
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
                    ViewField::make('location_map')
                        ->hiddenLabel()
                        ->dehydrated(false)
                        ->view('fieldops::filament.forms.structure-location-picker')
                        ->viewData([
                            'terrainLabel' => $locationDefaults['terrain_label'],
                            'defaultLat' => $locationDefaults['lat'],
                            'defaultLng' => $locationDefaults['lng'],
                            'defaultZoom' => $locationDefaults['zoom'],
                            'latInputId' => 'form.lat',
                            'lngInputId' => 'form.lng',
                            'centerLatInputId' => 'form.map_center_lat',
                            'centerLngInputId' => 'form.map_center_lng',
                        ])
                        ->columnSpanFull(),
                ]),
            Section::make(__('fieldops::resource.structures.fields.info'))
                ->columnSpanFull()
                ->schema([
                    // Single field in the admin's current locale (app()->getLocale(),
                    // set per-request by SetPanelLocale) — HasAiTranslations
                    // auto-translates to the other 3 canonical locales on save.
                    Textarea::make('info')
                        ->label(__('fieldops::resource.structures.fields.info'))
                        ->rows(3),
                ])
                ->collapsible()
                ->collapsed(),
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
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    /**
     * @param array<int, int|string> $terrainIds
     * @return array{lat: float, lng: float, zoom: int, terrain_label: ?string}
     */
    protected static function resolveLocationDefaults(array $terrainIds = []): array
    {
        $fallbackLat = 51.1635;
        $fallbackLng = 5.1640;
        $fallbackZoom = 16;

        $terrainId = collect($terrainIds)
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->first();

        if ($terrainId) {
            $terrain = Terrain::query()->with('complex')->find($terrainId);

            if ($terrain) {
                $location = static::hasCoordinates($terrain)
                    ? ['lat' => (float) $terrain->lat, 'lng' => (float) $terrain->lng, 'zoom' => 17]
                    : ($terrain->complex && static::hasCoordinates($terrain->complex)
                        ? ['lat' => (float) $terrain->complex->lat, 'lng' => (float) $terrain->complex->lng, 'zoom' => 16]
                        : null);

                if ($location) {
                    return $location + [
                        'terrain_label' => $terrain->getTranslation('name', app()->getLocale(), false)
                            ?: $terrain->getTranslation('name', 'nl', false)
                            ?: '#'.$terrain->id,
                    ];
                }
            }
        }

        return [
            'lat' => $fallbackLat,
            'lng' => $fallbackLng,
            'zoom' => $fallbackZoom,
            'terrain_label' => null,
        ];
    }

    protected static function hasCoordinates($record): bool
    {
        return is_numeric($record->lat ?? null) && is_numeric($record->lng ?? null);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('profile_header')
                ->hiddenLabel()
                ->state(fn (Structure $record) => [
                    'eyebrow' => static::getModelLabel(),
                    'name' => static::getRecordTitle($record),
                    'chips' => array_values(array_filter([
                        $record->structureType ? [
                            'label' => $record->structureType->getTranslation('name', app()->getLocale(), false)
                                ?: $record->structureType->getTranslation('name', 'nl', false),
                            'color' => 'info',
                        ] : null,
                        $record->accessType ? [
                            'label' => trim(($record->accessType->getTranslation('name', app()->getLocale(), false) ?: $record->accessType->getTranslation('name', 'nl', false))
                                .' · '.($record->access_active ? __('fieldops::resource.structures.status.access_active') : __('fieldops::resource.structures.status.access_inactive'))),
                            'color' => $record->access_active ? 'success' : 'warning',
                        ] : null,
                        $record->safetyType ? [
                            'label' => trim(($record->safetyType->getTranslation('name', app()->getLocale(), false) ?: $record->safetyType->getTranslation('name', 'nl', false))
                                .' · '.($record->safety_certified ? __('fieldops::resource.structures.status.safety_certified') : __('fieldops::resource.structures.status.safety_uncertified'))),
                            'color' => $record->safety_certified ? 'success' : 'warning',
                        ] : null,
                    ])),
                    'stat' => [
                        'value' => $record->height ?? '—',
                        'label' => __('fieldops::resource.structures.fields.height'),
                    ],
                ])
                ->view('fieldops::filament.infolists.profile-header')
                ->columnSpanFull(),

            ViewEntry::make('map_panel')
                ->hiddenLabel()
                ->state(fn (Structure $record) => static::buildMapPanelState($record))
                ->view('fieldops::filament.infolists.map-panel')
                ->columnSpanFull(),

            Section::make(__('fieldops::resource.media.section_label'))
                ->schema([
                    ViewEntry::make('photos')
                        ->label(__('fieldops::resource.media.photos'))
                        ->state(fn ($record) => $record->getMedia('photos'))
                        // Entry::getState() collapses an empty Collection to null (Laravel's
                        // blank() treats 0-count as blank) — ->default() backfills it so the
                        // blade always gets an iterable, never null. Same gotcha as ViewFoClient.
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
    protected static function buildMapPanelState(Structure $record): array
    {
        $record->loadMissing([
            'structureType',
            'terrains.terrainType',
            'electricalBoards.electricalBoardType',
        ]);

        $structureMarker = static::hasCoordinates($record)
            ? collect([[
                'type' => 'structure',
                'label' => $record->structureType?->getTranslation('name', app()->getLocale(), false)
                    ?: $record->structureType?->getTranslation('name', 'nl', false)
                    ?: '#'.$record->id,
                'description' => $record->height ? __('fieldops::resource.structures.fields.height').': '.$record->height.' cm' : null,
                'lat' => $record->lat,
                'lng' => $record->lng,
                'hasCoordinates' => true,
                'url' => null,
            ]])
            : collect();

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

        $items = $structureMarker
            ->concat($terrainMarkers)
            ->concat($boardMarkers)
            ->values()
            ->all();

        return [
            'title' => 'Map overview',
            'subtitle' => 'Desktop location map for this structure, its terrains, and its electrical boards.',
            'emptyTitle' => 'No coordinates available yet',
            'emptyDescription' => 'Add coordinates to this structure or one of its related records to show the map.',
            'summary' => [
                ['value' => 1, 'label' => __('fieldops::resource.structures.model_label')],
                ['value' => $record->terrains->count(), 'label' => __('fieldops::resource.terrains.plural_label')],
                ['value' => $record->electricalBoards->count(), 'label' => __('fieldops::resource.electrical_boards.plural_label')],
            ],
            'items' => $items,
            'markers' => array_values(array_filter($items, fn ($item) => ! empty($item['hasCoordinates']))),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('structureType.name')
                    ->label(__('fieldops::resource.structures.fields.structure_type'))
                    ->getStateUsing(fn ($record) =>
                        $record->structureType?->getTranslation('name', app()->getLocale(), false)
                        ?: $record->structureType?->getTranslation('name', 'nl', false)
                    )
                    ->badge()
                    ->color('info'),
                TextColumn::make('height')
                    ->label(__('fieldops::resource.structures.fields.height'))
                    ->suffix(' cm')
                    ->sortable(),
                TextColumn::make('terrains_count')
                    ->label(__('fieldops::resource.structures.fields.terrains_count'))
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['structureType', 'accessType', 'safetyType', 'terrains'])
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getRelations(): array
    {
        return [
            TerrainsRelationManager::class,
            LuminaireFramesRelationManager::class,
            ElectricalBoardsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListStructures::route('/'),
            'create' => CreateStructure::route('/create'),
            'view'   => ViewStructure::route('/{record}'),
            'edit'   => EditStructure::route('/{record}/edit'),
        ];
    }
}
