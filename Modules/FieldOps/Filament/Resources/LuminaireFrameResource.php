<?php

namespace Modules\FieldOps\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ViewField;
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
use Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages\CreateLuminaireFrame;
use Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages\EditLuminaireFrame;
use Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages\ListLuminaireFrames;
use Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages\ViewLuminaireFrame;
use Modules\FieldOps\Filament\Resources\LuminaireFrames\RelationManagers\LuminairesRelationManager;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\FieldOps\Models\LuminaireType;

class LuminaireFrameResource extends Resource
{
    protected static ?string $model = LuminaireFrame::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    // LuminaireFrame has no "name" column — see StructureResource::getRecordTitle()
    // for why hasRecordTitle() also needs overriding alongside this.
    public static function hasRecordTitle(): bool
    {
        return true;
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        if (! $record instanceof LuminaireFrame) {
            return static::getModelLabel();
        }

        return '#'.$record->id.($record->frameType?->name ? " — {$record->frameType->name}" : '');
    }

    protected static ?int $navigationSort = 5;

    /**
     * frame_x / frame_y are stored as normalized coordinates between 0 and 1.
     * Older records may still contain percentage-style values, so we accept both
     * and normalize them to the 0..1 space used by the frontend canvas.
     */
    public static function normalizeFrameCoordinate(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $numeric = (float) $value;

        return $numeric > 1.0 ? $numeric / 100 : $numeric;
    }

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
        return __('fieldops::resource.luminaire_frames.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.luminaire_frames.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.luminaire_frames.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    ViewField::make('luminaire_frame_type_id')
                        ->label(__('fieldops::resource.luminaire_frames.fields.frame_type'))
                        ->helperText(__('fieldops::resource.luminaire_frames.gallery.helper'))
                        ->view('fieldops::filament.forms.luminaire-frame-gallery-selector')
                        ->viewData([
                            'frames' => static::buildFrameTypeGalleryFrames(),
                        ])
                        ->columnSpanFull()
                        ->required(),
                ]),
        ]);
    }

    /**
     * @return array<int, array{id: int|string, title: string, typeLabel: string, indicator: string, previewUrl: ?string, previewAlt: string, hasPreview: bool}>
     */
    protected static function buildFrameTypeGalleryFrames(): array
    {
        return LuminaireFrameType::query()
            ->orderBy('name')
            ->get()
            ->map(function (LuminaireFrameType $frameType): array {
                $previewUrl = static::resolveFrameTypePreviewUrl($frameType->image);
                $indicator = '#'.$frameType->id;

                return [
                    'id' => $frameType->id,
                    'title' => $frameType->name,
                    'typeLabel' => $frameType->name,
                    'indicator' => $indicator,
                    'previewUrl' => $previewUrl,
                    'previewAlt' => $frameType->name.' '.$indicator,
                    'hasPreview' => $previewUrl !== null,
                ];
            })
            ->all();
    }

    protected static function resolveFrameTypePreviewUrl(?string $image): ?string
    {
        if (! $image) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, 'data:')) {
            return $image;
        }

        return asset(ltrim($image, '/'));
    }

    protected static function resolveMarkerImageUrl(?string $image): ?string
    {
        if (! $image) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, 'data:')) {
            return $image;
        }

        return asset(ltrim($image, '/'));
    }

    protected static function resolveMarkerPlaceholderUrl(): string
    {
        return asset('assets/luminaire-subgroups/image_placeholder.png');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('spatial_layout')
                ->hiddenLabel()
                ->state(fn (LuminaireFrame $record) => static::buildSpatialLayoutState($record))
                ->view('fieldops::filament.infolists.luminaire-frame-spatial-layout')
                ->columnSpanFull(),
        ]);
    }

    /**
     * @return array{
     *     eyebrow: string,
     *     title: string,
     *     subtitle: string,
     *     frameType: ?string,
     *     frameImage: ?string,
     *     summary: array<int, array{label: string, value: int|string}>,
     *     bounds: ?array{minX: float, maxX: float, minY: float, maxY: float},
     *     markers: array<int, array{
     *         id: int,
     *         label: string,
     *         serial: ?string,
     *         title: string,
     *         subgroup: ?string,
     *         frameX: float|int|string|null,
     *         frameY: float|int|string|null,
     *         scaleX: float|int|string|null,
     *         scaleY: float|int|string|null,
     *         positionVersion: int,
     *         positionSource: ?string,
     *         positionVerifiedAt: ?string,
     *         positionLabel: string,
     *         imageUrl: ?string,
     *         hasImage: bool,
     *         left: float,
     *         top: float,
     *         size: int,
     *         flagged: bool,
     *         selected: bool,
     *         url: string,
     *     }>,
     *     unpositioned: array<int, array{
     *         id: int,
     *         label: string,
     *         serial: ?string,
     *         title: string,
     *         subgroup: ?string,
     *         frameX: float|int|string|null,
     *         frameY: float|int|string|null,
     *         scaleX: float|int|string|null,
     *         scaleY: float|int|string|null,
     *         positionVersion: int,
     *         positionSource: ?string,
     *         positionVerifiedAt: ?string,
     *         positionLabel: string,
     *         imageUrl: ?string,
     *         hasImage: bool,
     *         flagged: bool,
     *         selected: bool,
     *         url: string,
     *     }>,
     *     selectedId: ?int,
     *     selectedMarker: ?array<string, mixed>,
     *     frameId: int,
     *     createUrl: string,
     *     luminaireTypes: array<int, array{id: int, name: string, productFamily: ?string, modelReference: ?string, typicalApplication: ?string, subgroupId: int, subgroupLabel: string, imageUrl: string, hasImage: bool}>,
     * }
     */
    protected static function buildSpatialLayoutState(LuminaireFrame $record): array
    {
        $luminaires = $record->luminaires()
            ->with([
                'luminaireType',
                'subgroup',
                'position' => fn ($query) => $query->withCount([
                    'maintenanceRecords',
                    'maintenanceRecords as open_issues_count' => fn (Builder $query) => $query
                        ->whereNotNull('problem_reported_at')
                        ->whereNull('problem_solved_at'),
                ]),
            ])
            ->withCount([
                'maintenanceRecords',
                'maintenanceRecords as open_issues_count' => fn ($query) => $query
                    ->whereNotNull('problem_reported_at')
                    ->whereNull('problem_solved_at'),
            ])
            ->orderBy('frame_position')
            ->get();
        $frameImage = static::resolveFrameTypePreviewUrl($record->frameType?->image);
        $placeholderImage = static::resolveMarkerPlaceholderUrl();

        $items = $luminaires->map(function (Luminaire $luminaire) use ($placeholderImage): array {
            $maintenanceCount = (int) ($luminaire->position?->maintenance_records_count ?? $luminaire->maintenance_records_count);
            $openIssuesCount = (int) ($luminaire->position?->open_issues_count ?? $luminaire->open_issues_count);
            $hasOpenIssue = $openIssuesCount > 0;
            $imageUrl = static::resolveMarkerImageUrl($luminaire->luminaireType?->image) ?? static::resolveMarkerPlaceholderUrl();

            return [
                'id' => $luminaire->id,
                'label' => (string) ($luminaire->frame_position ?? '?'),
                'serial' => $luminaire->serial_number,
                'title' => $luminaire->luminaireType?->name ?? __('fieldops::resource.luminaires.model_label'),
                'subgroup' => $luminaire->subgroup
                    ? "{$luminaire->subgroup->group_name} — {$luminaire->subgroup->brand}"
                    : null,
                'frameX' => $luminaire->frame_x,
                'frameY' => $luminaire->frame_y,
                'scaleX' => $luminaire->scale_x,
                'scaleY' => $luminaire->scale_y,
                'positionVersion' => (int) ($luminaire->position_version ?? 1),
                'positionSource' => $luminaire->position_source,
                'positionVerifiedAt' => $luminaire->position_verified_at?->toIso8601String(),
                'positionLabel' => self::normalizeFrameCoordinate($luminaire->frame_x) !== null && self::normalizeFrameCoordinate($luminaire->frame_y) !== null
                    ? 'X '.number_format(self::normalizeFrameCoordinate($luminaire->frame_x), 1).' · Y '.number_format(self::normalizeFrameCoordinate($luminaire->frame_y), 1)
                    : __('fieldops::resource.luminaire_frames.view.no_position'),
                'imageUrl' => $imageUrl,
                'hasImage' => $imageUrl !== $placeholderImage,
                'flagged' => $hasOpenIssue,
                'url' => \Modules\FieldOps\Filament\Resources\LuminaireResource::getUrl('view', ['record' => $luminaire]),
                'maintenanceCreateUrl' => \Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource::getUrl('create', [
                    'maintainable_type' => Luminaire::class,
                    'maintainable_id' => $luminaire->id,
                    'return_luminaire' => $luminaire->id,
                ]),
                'maintenanceIndexUrl' => \Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource::getUrl('index', [
                    'luminaire' => $luminaire->id,
                    'position' => $luminaire->luminaire_position_id,
                ]),
                'maintenanceCount' => $maintenanceCount,
                'updateUrl' => route('fieldops.luminaire-frame-editor.luminaires.update', ['luminaire' => $luminaire]),
                'hasCoordinates' => self::normalizeFrameCoordinate($luminaire->frame_x) !== null && self::normalizeFrameCoordinate($luminaire->frame_y) !== null,
            ];
        });

        $positioned = $items->filter(fn (array $item) => $item['hasCoordinates'])->values();
        $unpositioned = $items->reject(fn (array $item) => $item['hasCoordinates'])->values();

        $selectedId = request()->integer('luminaire') ?: request()->integer('selected');
        if ($selectedId !== null && ! $items->contains(fn (array $item) => $item['id'] === $selectedId)) {
            $selectedId = null;
        }

        if ($selectedId === null) {
            $selectedId = $positioned->first()['id'] ?? $items->first()['id'] ?? null;
        }

        $selectedMarker = $selectedId !== null
            ? $items->firstWhere('id', $selectedId)
            : null;

        if ($positioned->isNotEmpty()) {
            $xs = $positioned->pluck('frameX')->map(fn ($value) => (float) (self::normalizeFrameCoordinate($value) ?? 0));
            $ys = $positioned->pluck('frameY')->map(fn ($value) => (float) (self::normalizeFrameCoordinate($value) ?? 0));
            $minX = (float) $xs->min();
            $maxX = (float) $xs->max();
            $minY = (float) $ys->min();
            $maxY = (float) $ys->max();
            $positioned = $positioned->map(function (array $item) use ($selectedId): array {
                $left = max(0.0, min(100.0, (self::normalizeFrameCoordinate($item['frameX']) ?? 0.5) * 100));
                $top = max(0.0, min(100.0, (self::normalizeFrameCoordinate($item['frameY']) ?? 0.5) * 100));
                $scale = max((float) ($item['scaleX'] ?? 1.0), 0.65);

                return array_merge($item, [
                    'left' => round($left, 1),
                    'top' => round($top, 1),
                    'size' => (int) round(max(24, min(60, 26 * $scale))),
                    'selected' => $selectedId !== null && $item['id'] === $selectedId,
                ]);
            });

            $unpositioned = $unpositioned->map(fn (array $item): array => array_merge($item, [
                'selected' => $selectedId !== null && $item['id'] === $selectedId,
            ]));

            $bounds = [
                'minX' => round($minX, 2),
                'maxX' => round($maxX, 2),
                'minY' => round($minY, 2),
                'maxY' => round($maxY, 2),
            ];
        } else {
            $bounds = null;
            $positioned = $positioned->map(function (array $item) use ($selectedId): array {
                return array_merge($item, [
                    'left' => max(0.0, min(100.0, (self::normalizeFrameCoordinate($item['frameX']) ?? 0.5) * 100)),
                    'top' => max(0.0, min(100.0, (self::normalizeFrameCoordinate($item['frameY']) ?? 0.5) * 100)),
                    'size' => 30,
                    'selected' => $selectedId !== null && $item['id'] === $selectedId,
                ]);
            });
            $unpositioned = $unpositioned->map(fn (array $item): array => array_merge($item, [
                'selected' => $selectedId !== null && $item['id'] === $selectedId,
            ]));
        }

        $openIssues = $items->filter(fn (array $item) => $item['flagged'])->count();
        $latestVerifiedAt = $luminaires
            ->pluck('position_verified_at')
            ->filter()
            ->sortDesc()
            ->first();
        $latestVerifiedLabel = $latestVerifiedAt
            ? $latestVerifiedAt->copy()->locale(app()->getLocale())->translatedFormat('d M Y')
            : __('fieldops::resource.luminaire_frames.view.not_verified');

        return [
            'eyebrow' => __('fieldops::resource.luminaire_frames.view.eyebrow'),
            'title' => static::getRecordTitle($record),
            'subtitle' => __('fieldops::resource.luminaire_frames.view.subtitle'),
            'frameType' => $record->frameType?->name,
            'frameImage' => $frameImage,
            'summary' => [
                ['label' => __('fieldops::resource.luminaire_frames.view.summary_total'), 'value' => $items->count()],
                ['label' => __('fieldops::resource.luminaire_frames.view.summary_unpositioned'), 'value' => $unpositioned->count()],
                ['label' => __('fieldops::resource.luminaire_frames.view.summary_open_issues'), 'value' => $openIssues],
                ['label' => __('fieldops::resource.luminaire_frames.view.summary_last_verified'), 'value' => $latestVerifiedLabel],
            ],
            'bounds' => $bounds,
            'markers' => $positioned->values()->all(),
            'unpositioned' => $unpositioned->values()->all(),
            'selectedId' => $selectedId,
            'selectedMarker' => $selectedMarker,
            'frameId' => (int) $record->getKey(),
            'createUrl' => route('fieldops.luminaire-frame-editor.luminaires.store'),
            'luminaireTypes' => LuminaireType::query()
                ->with('subgroup')
                ->whereHas('subgroup')
                ->orderBy('name')
                ->get()
                ->map(fn (LuminaireType $type): array => [
                    'id' => (int) $type->getKey(),
                    'name' => $type->name,
                    'productFamily' => $type->product_family,
                    'modelReference' => $type->model_reference,
                    'typicalApplication' => $type->typical_application,
                    'subgroupId' => (int) $type->luminaire_subgroup_id,
                    'subgroupLabel' => "{$type->subgroup->group_name} — {$type->subgroup->brand}",
                    'imageUrl' => static::resolveMarkerImageUrl($type->image) ?? $placeholderImage,
                    'hasImage' => static::resolveMarkerImageUrl($type->image) !== null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * frame_x/frame_y are treated as relative coordinates inside the frame
     * background, so the layout keeps the same coordinate space as the frontend
     * editor. scale_x drives marker size; a marker is flagged (amber) when its
     * luminaire has an open maintenance issue — problem_reported_at set,
     * problem_solved_at still null.
     *
     * Public (not just used by this resource's own infolist): LuminaireResource
     * reuses this to draw the same frame layout on a single Luminaire's own view
     * page, with that luminaire's own marker highlighted via $selectedLuminaireId.
     *
     * @return array<int, array{id: int, left: float, top: float, size: int, label: string, serial: ?string, imageUrl: ?string, hasImage: bool, flagged: bool, selected: bool, url: string}>
     */
    public static function buildCanvasMarkers(LuminaireFrame $record, ?int $selectedLuminaireId = null): array
    {
        $luminaires = $record->luminaires()->with('luminaireType')->orderBy('frame_position')->get();
        $placeholderImage = static::resolveMarkerPlaceholderUrl();

        if ($luminaires->isEmpty()) {
            return [];
        }

        return $luminaires->map(function (Luminaire $luminaire) use ($selectedLuminaireId, $placeholderImage) {
            $hasOpenIssue = $luminaire->maintenanceRecords()
                ->whereNotNull('problem_reported_at')
                ->whereNull('problem_solved_at')
                ->exists();
            $frameX = self::normalizeFrameCoordinate($luminaire->frame_x);
            $frameY = self::normalizeFrameCoordinate($luminaire->frame_y);
            $imageUrl = static::resolveMarkerImageUrl($luminaire->luminaireType?->image) ?? $placeholderImage;

            return [
                'id' => $luminaire->id,
                'left' => round(max(0.0, min(100.0, ($frameX ?? 0.5) * 100)), 1),
                'top' => round(max(0.0, min(100.0, ($frameY ?? 0.5) * 100)), 1),
                'size' => (int) round(28 * max((float) ($luminaire->scale_x ?? 1.0), 0.5)),
                'label' => (string) ($luminaire->frame_position ?? '?'),
                'serial' => $luminaire->serial_number,
                'positionVersion' => (int) ($luminaire->position_version ?? 1),
                'positionSource' => $luminaire->position_source,
                'positionVerifiedAt' => $luminaire->position_verified_at?->toIso8601String(),
                'imageUrl' => $imageUrl,
                'hasImage' => $imageUrl !== $placeholderImage,
                'flagged' => $hasOpenIssue,
                'selected' => $selectedLuminaireId !== null && $luminaire->id === $selectedLuminaireId,
                'url' => \Modules\FieldOps\Filament\Resources\LuminaireResource::getUrl('edit', ['record' => $luminaire]),
            ];
        })->values()->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('frameType.name')
                    ->label(__('fieldops::resource.luminaire_frames.fields.frame_type'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('luminaires_count')
                    ->label(__('fieldops::resource.luminaire_frames.fields.luminaires_count'))
                    ->counts('luminaires')
                    ->sortable(),
                TextColumn::make('structures_count')
                    ->label(__('fieldops::resource.luminaire_frames.fields.structures_count'))
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

    public static function getRelations(): array
    {
        return [
            LuminairesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['frameType'])
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLuminaireFrames::route('/'),
            'create' => CreateLuminaireFrame::route('/create'),
            'view' => ViewLuminaireFrame::route('/{record}'),
            'edit' => EditLuminaireFrame::route('/{record}/edit'),
        ];
    }
}
