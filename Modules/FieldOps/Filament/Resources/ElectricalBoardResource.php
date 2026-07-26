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
use Filament\Forms\Components\ViewField;
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
use Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages\CreateElectricalBoard;
use Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages\EditElectricalBoard;
use Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages\ListElectricalBoards;
use Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages\ViewElectricalBoard;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\ElectricalBoardType;

class ElectricalBoardResource extends Resource
{
    protected static ?string $model = ElectricalBoard::class;

    protected static ?string $recordTitleAttribute = 'location_description';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?int $navigationSort = 7;

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
        return __('fieldops::resource.electrical_boards.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.electrical_boards.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.electrical_boards.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        $locationDefaults = static::resolveLocationDefaults(request()->integer('complex_id'));

        return $schema->components([
            Section::make()->schema([
                Select::make('electrical_board_type_id')
                    ->label(__('fieldops::resource.electrical_boards.fields.electrical_board_type'))
                    ->options(ElectricalBoardType::all()->mapWithKeys(fn ($t) => [
                        $t->id => $t->getTranslation('name', app()->getLocale(), false)
                            ?: $t->getTranslation('name', 'nl', false),
                    ]))
                    ->searchable()
                    ->required(),
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
            ])->columns(2),
            Section::make(__('fieldops::resource.electrical_boards.fields.location_description'))->schema([
                // Single field in the admin's current locale (app()->getLocale(),
                // set per-request by SetPanelLocale) — HasAiTranslations
                // auto-translates to the other 3 canonical locales on save.
                Textarea::make('location_description')
                    ->label(__('fieldops::resource.electrical_boards.fields.location_description'))
                    ->rows(3),
            ])->collapsible()->collapsed(),
            Section::make(__('fieldops::resource.electrical_boards.map.section_label'))
                ->columnSpanFull()
                ->schema([
                    ViewField::make('location_map')
                        ->hiddenLabel()
                        ->dehydrated(false)
                        ->view('fieldops::filament.forms.electrical-board-location-picker')
                        ->viewData([
                            'complexLabel' => $locationDefaults['complex_label'],
                            'defaultLat' => $locationDefaults['lat'],
                            'defaultLng' => $locationDefaults['lng'],
                            'defaultZoom' => $locationDefaults['zoom'],
                            'latInputId' => 'form.lat',
                            'lngInputId' => 'form.lng',
                            'centerLatInputId' => 'form.map_center_lat',
                            'centerLngInputId' => 'form.map_center_lng',
                        ])
                        ->columnSpanFull(),
                ])->collapsible()->collapsed(false),
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
            Section::make()->schema([
                TextEntry::make('electricalBoardType.name')
                    ->label(__('fieldops::resource.electrical_boards.fields.electrical_board_type'))
                    ->getStateUsing(fn ($record) => $record->electricalBoardType?->getTranslation('name', app()->getLocale(), false)
                        ?: $record->electricalBoardType?->getTranslation('name', 'nl', false))
                    ->placeholder('—')
                    ->badge()
                    ->color('warning'),
                TextEntry::make('location_description')
                    ->label(__('fieldops::resource.electrical_boards.fields.location_description'))
                    ->getStateUsing(fn ($record) => $record->getTranslation('location_description', app()->getLocale(), false)
                        ?: $record->getTranslation('location_description', 'nl', false))
                    ->placeholder('—'),
            ])->columns(2),

            ViewEntry::make('map_panel')
                ->hiddenLabel()
                ->state(fn (ElectricalBoard $record) => static::buildMapPanelState($record))
                ->view('fieldops::filament.infolists.map-panel')
                ->columnSpanFull(),

            Section::make(__('fieldops::resource.electrical_boards.used_by_label'))
                ->schema([
                    ViewEntry::make('used_by')
                        ->hiddenLabel()
                        ->state(fn (ElectricalBoard $record) => static::buildUsedByGroups($record))
                        ->view('fieldops::filament.infolists.used-by'),
                ]),

            Section::make(__('fieldops::resource.media.section_label'))
                ->schema([
                    ViewEntry::make('photos')
                        ->label(__('fieldops::resource.media.photos'))
                        ->state(fn ($record) => $record->getMedia('photos'))
                        ->default(fn () => collect())
                        ->view('fieldops::filament.infolists.media-gallery'),
                    ViewEntry::make('documents')
                        ->label(__('fieldops::resource.media.documents'))
                        ->state(fn ($record) => $record->getMedia('documents'))
                        ->default(fn () => collect())
                        ->view('fieldops::filament.infolists.document-list'),
                ])
                ->collapsible()
                ->collapsed(fn ($record) => $record->getMedia('photos')->isEmpty() && $record->getMedia('documents')->isEmpty()),
        ]);
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

    protected static function hasCoordinates($record): bool
    {
        return is_numeric($record->lat ?? null) && is_numeric($record->lng ?? null);
    }

    /**
     * @return array{title: string, subtitle: string, emptyTitle: string, emptyDescription: string, summary: array<int, array{label: string, value: int|string}>, markers: array<int, array<string, mixed>>}
     */
    protected static function buildMapPanelState(ElectricalBoard $record): array
    {
        $record->loadMissing([
            'electricalBoardType',
            'complexes',
            'terrains.terrainType',
            'structures.structureType',
        ]);

        $boardMarker = static::hasCoordinates($record)
            ? collect([[
                'type' => 'electrical-board',
                'label' => $record->electricalBoardType?->getTranslation('name', app()->getLocale(), false)
                    ?: $record->electricalBoardType?->getTranslation('name', 'nl', false)
                    ?: '#'.$record->id,
                'description' => $record->getTranslation('location_description', app()->getLocale(), false)
                    ?: $record->getTranslation('location_description', 'nl', false),
                'lat' => $record->lat,
                'lng' => $record->lng,
                'hasCoordinates' => true,
                'url' => null,
            ]])
            : collect();

        $complexMarkers = $record->complexes
            ->map(fn ($complex) => [
                'type' => 'complex',
                'label' => $complex->name,
                'description' => collect([$complex->street, $complex->zipcode, $complex->city])->filter()->implode(', '),
                'lat' => $complex->lat,
                'lng' => $complex->lng,
                'hasCoordinates' => static::hasCoordinates($complex),
                'url' => ComplexResource::getUrl('view', ['record' => $complex]),
            ]);

        $terrainMarkers = $record->terrains
            ->map(fn ($terrain) => [
                'type' => 'terrain',
                'label' => $terrain->getTranslation('name', app()->getLocale(), false)
                    ?: $terrain->getTranslation('name', 'nl', false)
                    ?: '#'.$terrain->id,
                'description' => $terrain->terrainType?->getTranslation('type', app()->getLocale(), false)
                    ?: $terrain->terrainType?->getTranslation('type', 'nl', false),
                'terrainTypeCode' => $terrain->terrainType?->code,
                'terrainTypeColor' => $terrain->terrainType?->pin_color,
                'lat' => $terrain->lat,
                'lng' => $terrain->lng,
                'hasCoordinates' => static::hasCoordinates($terrain),
                'url' => TerrainResource::getUrl('view', ['record' => $terrain]),
            ]);

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

        $items = $boardMarker
            ->concat($complexMarkers)
            ->concat($terrainMarkers)
            ->concat($structureMarkers)
            ->values()
            ->all();

        return [
            'title' => __('fieldops::resource.complexes.fields.map'),
            'subtitle' => 'Desktop location map for this electrical board and related FieldOps assets.',
            'emptyTitle' => 'No coordinates available yet',
            'emptyDescription' => 'Add coordinates to this electrical board or one of its used-by records to show the map.',
            'summary' => [
                ['value' => 1, 'label' => __('fieldops::resource.electrical_boards.model_label')],
                ['value' => $record->complexes->count() + $record->terrains->count() + $record->structures->count(), 'label' => __('fieldops::resource.electrical_boards.used_by_label')],
            ],
            'items' => $items,
            'markers' => array_values(array_filter($items, fn ($item) => ! empty($item['hasCoordinates']))),
        ];
    }

    /**
     * ElectricalBoard has no single owner (Complex/Terrain/Structure are all
     * belongsToMany, Pattern C) — this view stays read-only "used by" on purpose.
     * Attach/detach for these same 3 pivots already lives on Structure's and
     * Terrain's own pages (their side of the relationship); Complex's side now
     * has attach/create flow in its relation manager.
     *
     * @return array<int, array{label: string, items: array<int, array{label: string, url: string}>}>
     */
    protected static function buildUsedByGroups(ElectricalBoard $record): array
    {
        return [
            [
                'label' => __('fieldops::resource.complexes.plural_label'),
                'items' => $record->complexes()->get()->map(fn ($complex) => [
                    'label' => $complex->name,
                    'url' => \Modules\FieldOps\Filament\Resources\ComplexResource::getUrl('edit', ['record' => $complex]),
                ])->all(),
            ],
            [
                'label' => __('fieldops::resource.terrains.plural_label'),
                'items' => $record->terrains()->get()->map(fn ($terrain) => [
                    'label' => $terrain->getTranslation('name', app()->getLocale(), false) ?: $terrain->getTranslation('name', 'nl', false),
                    'url' => \Modules\FieldOps\Filament\Resources\TerrainResource::getUrl('view', ['record' => $terrain]),
                ])->all(),
            ],
            [
                'label' => __('fieldops::resource.structures.plural_label'),
                'items' => $record->structures()->with('structureType')->get()->map(fn ($structure) => [
                    'label' => '#'.$structure->id.' — '.($structure->structureType?->getTranslation('name', app()->getLocale(), false) ?: $structure->structureType?->getTranslation('name', 'nl', false)),
                    'url' => \Modules\FieldOps\Filament\Resources\StructureResource::getUrl('view', ['record' => $structure]),
                ])->all(),
            ],
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('electricalBoardType.name')
                    ->label(__('fieldops::resource.electrical_boards.fields.electrical_board_type'))
                    ->getStateUsing(fn ($record) =>
                        $record->electricalBoardType?->getTranslation('name', app()->getLocale(), false)
                        ?: $record->electricalBoardType?->getTranslation('name', 'nl', false)
                    )
                    ->badge()
                    ->color('warning'),
                TextColumn::make('complexes_count')
                    ->label(__('fieldops::resource.electrical_boards.fields.complexes_count'))
                    ->counts('complexes')
                    ->sortable(),
                TextColumn::make('terrains_count')
                    ->label(__('fieldops::resource.electrical_boards.fields.terrains_count'))
                    ->counts('terrains')
                    ->sortable(),
                TextColumn::make('structures_count')
                    ->label(__('fieldops::resource.electrical_boards.fields.structures_count'))
                    ->counts('structures')
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
            ->with(['electricalBoardType'])
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListElectricalBoards::route('/'),
            'create' => CreateElectricalBoard::route('/create'),
            'view'   => ViewElectricalBoard::route('/{record}'),
            'edit'   => EditElectricalBoard::route('/{record}/edit'),
        ];
    }
}
