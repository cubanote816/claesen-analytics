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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
        return $schema->components([
            Section::make()->schema([
                Select::make('structure_type_id')
                    ->label(__('fieldops::resource.structures.fields.structure_type'))
                    ->options(StructureType::all()->mapWithKeys(fn ($t) => [
                        $t->id => $t->getTranslation('name', app()->getLocale(), false)
                            ?: $t->getTranslation('name', 'nl', false),
                    ]))
                    ->searchable()
                    ->nullable(),
                TextInput::make('height')
                    ->label(__('fieldops::resource.structures.fields.height'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('lat')
                    ->label(__('fieldops::resource.structures.fields.lat'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('lng')
                    ->label(__('fieldops::resource.structures.fields.lng'))
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
            ])->columns(2),
            Section::make(__('fieldops::resource.structures.fields.info'))->schema([
                // Single field in the admin's current locale (app()->getLocale(),
                // set per-request by SetPanelLocale) — HasAiTranslations
                // auto-translates to the other 3 canonical locales on save.
                Textarea::make('info')
                    ->label(__('fieldops::resource.structures.fields.info'))
                    ->rows(3),
            ])->collapsible()->collapsed(),
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
                Group::make([
                    TextEntry::make('structureType.name')
                        ->label(__('fieldops::resource.structures.fields.structure_type'))
                        ->getStateUsing(fn ($record) => $record->structureType?->getTranslation('name', app()->getLocale(), false)
                            ?: $record->structureType?->getTranslation('name', 'nl', false))
                        ->placeholder('—')
                        ->badge()
                        ->color('info'),
                    TextEntry::make('height')
                        ->label(__('fieldops::resource.structures.fields.height'))
                        ->suffix(' cm')
                        ->placeholder('—'),
                ]),
                Group::make([
                    TextEntry::make('access_status')
                        ->label(__('fieldops::resource.structures.fields.access_type'))
                        ->state(fn ($record) => $record->accessType
                            ? trim(($record->accessType->getTranslation('name', app()->getLocale(), false) ?: $record->accessType->getTranslation('name', 'nl', false))
                                .' · '.($record->access_active ? __('fieldops::resource.structures.status.access_active') : __('fieldops::resource.structures.status.access_inactive')))
                            : null)
                        ->placeholder('—')
                        ->badge()
                        ->color(fn ($record) => $record->access_active ? 'success' : 'warning'),
                    TextEntry::make('safety_status')
                        ->label(__('fieldops::resource.structures.fields.safety_type'))
                        ->state(fn ($record) => $record->safetyType
                            ? trim(($record->safetyType->getTranslation('name', app()->getLocale(), false) ?: $record->safetyType->getTranslation('name', 'nl', false))
                                .' · '.($record->safety_certified ? __('fieldops::resource.structures.status.safety_certified') : __('fieldops::resource.structures.status.safety_uncertified')))
                            : null)
                        ->placeholder('—')
                        ->badge()
                        ->color(fn ($record) => $record->safety_certified ? 'success' : 'warning'),
                ]),
                TextEntry::make('info')
                    ->label(__('fieldops::resource.structures.fields.info'))
                    ->getStateUsing(fn ($record) => $record->getTranslation('info', app()->getLocale(), false) ?: $record->getTranslation('info', 'nl', false))
                    ->placeholder('—')
                    ->columnSpanFull(),
            ])->columns(2),

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
                'url' => null,
            ]])
            : collect();

        $terrainMarkers = $record->terrains
            ->filter(fn ($terrain) => static::hasCoordinates($terrain))
            ->map(fn ($terrain) => [
                'type' => 'terrain',
                'label' => $terrain->getTranslation('name', app()->getLocale(), false)
                    ?: $terrain->getTranslation('name', 'nl', false)
                    ?: '#'.$terrain->id,
                'description' => $terrain->terrainType?->getTranslation('type', app()->getLocale(), false)
                    ?: $terrain->terrainType?->getTranslation('type', 'nl', false),
                'lat' => $terrain->lat,
                'lng' => $terrain->lng,
                'url' => TerrainResource::getUrl('view', ['record' => $terrain]),
            ]);

        $boardMarkers = $record->electricalBoards
            ->filter(fn ($board) => static::hasCoordinates($board))
            ->map(fn ($board) => [
                'type' => 'electrical-board',
                'label' => $board->electricalBoardType?->getTranslation('name', app()->getLocale(), false)
                    ?: $board->electricalBoardType?->getTranslation('name', 'nl', false)
                    ?: '#'.$board->id,
                'description' => $board->getTranslation('location_description', app()->getLocale(), false)
                    ?: $board->getTranslation('location_description', 'nl', false),
                'lat' => $board->lat,
                'lng' => $board->lng,
                'url' => ElectricalBoardResource::getUrl('view', ['record' => $board]),
            ]);

        return [
            'title' => 'Map overview',
            'subtitle' => 'Desktop location map for this structure, its terrains, and its electrical boards.',
            'emptyTitle' => 'No coordinates available yet',
            'emptyDescription' => 'Add coordinates to this structure or one of its related records to show the map.',
            'summary' => [
                ['value' => $structureMarker->count(), 'label' => __('fieldops::resource.structures.model_label')],
                ['value' => $terrainMarkers->count(), 'label' => __('fieldops::resource.terrains.plural_label')],
                ['value' => $boardMarkers->count(), 'label' => __('fieldops::resource.electrical_boards.plural_label')],
            ],
            'markers' => $structureMarker
                ->concat($terrainMarkers)
                ->concat($boardMarkers)
                ->values()
                ->all(),
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
