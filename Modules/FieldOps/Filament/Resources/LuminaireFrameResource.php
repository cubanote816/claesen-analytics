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
     *         positionLabel: string,
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
     *         positionLabel: string,
     *         flagged: bool,
     *         selected: bool,
     *         url: string,
     *     }>,
     *     selectedId: ?int,
     *     selectedMarker: ?array<string, mixed>,
     * }
     */
    protected static function buildSpatialLayoutState(LuminaireFrame $record): array
    {
        $luminaires = $record->luminaires()
            ->with(['luminaireType', 'subgroup'])
            ->orderBy('frame_position')
            ->get();

        $items = $luminaires->map(function (Luminaire $luminaire): array {
            $hasOpenIssue = $luminaire->maintenanceRecords()
                ->whereNotNull('problem_reported_at')
                ->whereNull('problem_solved_at')
                ->exists();

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
                'positionLabel' => is_numeric($luminaire->frame_x) && is_numeric($luminaire->frame_y)
                    ? 'X '.number_format((float) $luminaire->frame_x, 1).' · Y '.number_format((float) $luminaire->frame_y, 1)
                    : __('fieldops::resource.luminaire_frames.view.no_position'),
                'flagged' => $hasOpenIssue,
                'url' => \Modules\FieldOps\Filament\Resources\LuminaireResource::getUrl('view', ['record' => $luminaire]),
                'hasCoordinates' => is_numeric($luminaire->frame_x) && is_numeric($luminaire->frame_y),
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
            $xs = $positioned->pluck('frameX')->map(fn ($value) => (float) $value);
            $ys = $positioned->pluck('frameY')->map(fn ($value) => (float) $value);
            $minX = (float) $xs->min();
            $maxX = (float) $xs->max();
            $minY = (float) $ys->min();
            $maxY = (float) $ys->max();
            $rangeX = max($maxX - $minX, 1.0);
            $rangeY = max($maxY - $minY, 1.0);

            $positioned = $positioned->map(function (array $item) use ($minX, $rangeX, $minY, $rangeY, $selectedId): array {
                $left = 10 + ((((float) $item['frameX']) - $minX) / $rangeX) * 80;
                $top = 10 + ((((float) $item['frameY']) - $minY) / $rangeY) * 80;
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
                    'left' => 50.0,
                    'top' => 50.0,
                    'size' => 30,
                    'selected' => $selectedId !== null && $item['id'] === $selectedId,
                ]);
            });
            $unpositioned = $unpositioned->map(fn (array $item): array => array_merge($item, [
                'selected' => $selectedId !== null && $item['id'] === $selectedId,
            ]));
        }

        $openIssues = $items->filter(fn (array $item) => $item['flagged'])->count();

        return [
            'eyebrow' => __('fieldops::resource.luminaire_frames.view.eyebrow'),
            'title' => static::getRecordTitle($record),
            'subtitle' => __('fieldops::resource.luminaire_frames.view.subtitle', [
                'count' => $items->count(),
                'positioned' => $positioned->count(),
            ]),
            'frameType' => $record->frameType?->name,
            'summary' => [
                ['label' => __('fieldops::resource.luminaire_frames.view.summary_total'), 'value' => $items->count()],
                ['label' => __('fieldops::resource.luminaire_frames.view.summary_positioned'), 'value' => $positioned->count()],
                ['label' => __('fieldops::resource.luminaire_frames.view.summary_unpositioned'), 'value' => $unpositioned->count()],
                ['label' => __('fieldops::resource.luminaire_frames.view.summary_open_issues'), 'value' => $openIssues],
            ],
            'bounds' => $bounds,
            'markers' => $positioned->values()->all(),
            'unpositioned' => $unpositioned->values()->all(),
            'selectedId' => $selectedId,
            'selectedMarker' => $selectedMarker,
        ];
    }

    /**
     * frame_x/frame_y have no declared value range in the schema, so markers are
     * normalized to the actual min/max of this frame's luminaires (with padding)
     * instead of assuming a fixed 0-100 scale. scale_x drives marker size; a marker
     * is flagged (amber) when its luminaire has an open maintenance issue —
     * problem_reported_at set, problem_solved_at still null.
     *
     * Public (not just used by this resource's own infolist): LuminaireResource
     * reuses this to draw the same frame layout on a single Luminaire's own view
     * page, with that luminaire's own marker highlighted via $selectedLuminaireId.
     *
     * @return array<int, array{id: int, left: float, top: float, size: int, label: string, serial: ?string, flagged: bool, selected: bool, url: string}>
     */
    public static function buildCanvasMarkers(LuminaireFrame $record, ?int $selectedLuminaireId = null): array
    {
        $luminaires = $record->luminaires()->with('luminaireType')->orderBy('frame_position')->get();

        if ($luminaires->isEmpty()) {
            return [];
        }

        $xs = $luminaires->pluck('frame_x')->filter(fn ($v) => $v !== null);
        $ys = $luminaires->pluck('frame_y')->filter(fn ($v) => $v !== null);
        $minX = $xs->min() ?? 0.0;
        $minY = $ys->min() ?? 0.0;
        $rangeX = max(($xs->max() ?? 100) - $minX, 1);
        $rangeY = max(($ys->max() ?? 100) - $minY, 1);

        return $luminaires->map(function (Luminaire $luminaire) use ($minX, $rangeX, $minY, $rangeY, $selectedLuminaireId) {
            $hasOpenIssue = $luminaire->maintenanceRecords()
                ->whereNotNull('problem_reported_at')
                ->whereNull('problem_solved_at')
                ->exists();

            return [
                'id' => $luminaire->id,
                'left' => round(10 + (($luminaire->frame_x - $minX) / $rangeX) * 80, 1),
                'top' => round(10 + (($luminaire->frame_y - $minY) / $rangeY) * 80, 1),
                'size' => (int) round(28 * max((float) ($luminaire->scale_x ?? 1.0), 0.5)),
                'label' => (string) ($luminaire->frame_position ?? '?'),
                'serial' => $luminaire->serial_number,
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
            'index'  => ListLuminaireFrames::route('/'),
            'create' => CreateLuminaireFrame::route('/create'),
            'view'   => ViewLuminaireFrame::route('/{record}'),
            'edit'   => EditLuminaireFrame::route('/{record}/edit'),
        ];
    }
}
