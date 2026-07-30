<?php

namespace Modules\FieldOps\Filament\Resources;

use BackedEnum;
use Closure;
use Livewire\Component as LivewireComponent;
use Filament\Actions\BulkActionGroup;
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
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\CreateLuminaire;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\EditLuminaire;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\ListLuminaires;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\ViewLuminaire;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;

class LuminaireResource extends Resource
{
    protected static ?string $model = Luminaire::class;

    protected static ?string $recordTitleAttribute = 'serial_number';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static ?int $navigationSort = 6;

    // Luminaire never exists without a LuminaireFrame (fo_luminaires.luminaire_frame_id
    // is NOT NULL) — only reachable navigating from a Frame's Luminaires/canvas,
    // never as a flat sidebar entry.
    protected static bool $shouldRegisterNavigation = false;

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
        return __('fieldops::resource.luminaires.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.luminaires.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.luminaires.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('position_version')
                ->default(1),

            Section::make(__('fieldops::resource.luminaires.sections.identity'))
                ->description(__('fieldops::resource.luminaires.sections.identity_description'))
                ->schema([
                    ViewField::make('luminaire_type_id')
                        ->label(__('fieldops::resource.luminaires.fields.luminaire_type'))
                        ->view('fieldops::filament.forms.luminaire-type-gallery-selector')
                        // Locked on edit: changing the installed product must go through the
                        // "Replace luminaire" action (ViewLuminaire), which creates a new
                        // Luminaire row, retires this one and records maintenance atomically
                        // (CLA-265). Editing this field directly here would bypass all of that.
                        ->viewData(fn (string $operation, ?Luminaire $record): array => [
                            'types' => static::buildLuminaireTypeChoices(),
                            'locked' => $operation !== 'create',
                            'replaceUrl' => $record ? static::getUrl('view', ['record' => $record]) : null,
                        ])
                        ->columnSpanFull()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $set('luminaire_subgroup_id', $state ? LuminaireType::find($state)?->luminaire_subgroup_id : null);
                        })
                        ->required(),
                    Select::make('luminaire_subgroup_id')
                        ->label(__('fieldops::resource.luminaires.fields.subgroup'))
                        ->options(LuminaireSubgroup::orderBy('group_name')->get()
                            ->mapWithKeys(fn ($s) => [$s->id => "{$s->group_name} — {$s->brand}"])
                        )
                        ->searchable()
                        ->disabled()
                        ->dehydrated()
                        ->nullable(),
                    TextInput::make('serial_number')
                        ->label(__('fieldops::resource.luminaires.fields.serial_number'))
                        ->nullable()
                        ->maxLength(100),
                    Textarea::make('info')
                        ->label(__('fieldops::resource.luminaires.fields.info'))
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2)->columnSpanFull(),

            Section::make(__('fieldops::resource.luminaires.sections.frame_assignment'))
                ->description(__('fieldops::resource.luminaires.sections.frame_assignment_description'))
                ->schema([
                    // A luminaire can never be orphaned: it must resolve to a real physical
                    // location through Complex -> Terrain -> Structure -> Frame. These three
                    // helper selects are pure UI scaffolding (dehydrated(false), no DB column)
                    // that narrow the final `luminaire_frame_id` options at each step, instead
                    // of showing every frame in the system in one flat unscoped search.
                    Select::make('complex_id')
                        ->label(__('fieldops::resource.luminaires.fields.complex'))
                        ->options(fn () => Complex::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(function (Set $set): void {
                            $set('terrain_id', null);
                            $set('structure_id', null);
                            $set('luminaire_frame_id', null);
                        })
                        // Required only on create: a brand new luminaire must resolve through
                        // the full hierarchy. On edit the frame is already valid and existing
                        // frames aren't guaranteed to resolve a structure (LuminaireFrame allows
                        // creation without one) — the cascade there is a convenience filter only.
                        ->required(fn (string $operation): bool => $operation === 'create'),
                    Select::make('terrain_id')
                        ->label(__('fieldops::resource.luminaires.fields.terrain'))
                        ->options(fn (Get $get) => $get('complex_id')
                            ? Terrain::where('complex_id', $get('complex_id'))->get()->mapWithKeys(fn ($t) => [$t->id => $t->name])
                            : [])
                        ->searchable()
                        ->live()
                        ->dehydrated(false)
                        ->disabled(fn (Get $get) => blank($get('complex_id')))
                        ->afterStateUpdated(function (Set $set): void {
                            $set('structure_id', null);
                            $set('luminaire_frame_id', null);
                        })
                        ->required(fn (string $operation): bool => $operation === 'create'),
                    Select::make('structure_id')
                        ->label(__('fieldops::resource.luminaires.fields.structure'))
                        ->options(fn (Get $get) => $get('terrain_id')
                            ? Structure::whereHas('terrains', fn (Builder $q) => $q->where('fo_terrains.id', $get('terrain_id')))
                                ->with('structureType')
                                ->get()
                                ->mapWithKeys(fn ($s) => [$s->id => "#{$s->id} — {$s->structureType?->name}"])
                            : [])
                        ->searchable()
                        ->live()
                        ->dehydrated(false)
                        ->disabled(fn (Get $get) => blank($get('terrain_id')))
                        ->afterStateUpdated(fn (Set $set) => $set('luminaire_frame_id', null))
                        ->required(fn (string $operation): bool => $operation === 'create'),
                    Select::make('luminaire_frame_id')
                        ->label(__('fieldops::resource.luminaires.fields.frame'))
                        ->options(function (Get $get, ?Luminaire $record) {
                            $options = $get('structure_id')
                                ? LuminaireFrame::whereHas('structures', fn (Builder $q) => $q->where('fo_structures.id', $get('structure_id')))
                                    ->with('frameType')
                                    ->get()
                                    ->mapWithKeys(fn ($f) => [$f->id => "#{$f->id} — {$f->frameType?->name}"])
                                : collect();

                            // The cascade can't resolve a structure for every existing frame
                            // (LuminaireFrame allows creation without one) — always keep the
                            // record's current frame selectable on edit so the field stays
                            // valid even when its ancestry doesn't cleanly resolve.
                            if ($record?->luminaire_frame_id && ! $options->has($record->luminaire_frame_id)) {
                                $currentFrame = LuminaireFrame::with('frameType')->find($record->luminaire_frame_id);

                                if ($currentFrame) {
                                    $options->put($currentFrame->id, "#{$currentFrame->id} — {$currentFrame->frameType?->name}");
                                }
                            }

                            return $options;
                        })
                        ->searchable()
                        ->live()
                        ->disabled(fn (Get $get) => blank($get('structure_id')))
                        ->required()
                        // Changing the frame changes which positions count as "occupied" for the
                        // sibling frame_position field — re-run just that field's validation so a
                        // conflict error raised against the previous frame doesn't linger stale.
                        // Caught and applied to the error bag directly rather than left to
                        // propagate: Livewire only auto-converts a thrown ValidationException
                        // into the error bag at the request boundary, which doesn't exist when
                        // afterStateUpdated fires synchronously inside a test's fillForm().
                        ->afterStateUpdated(function (Select $component, LivewireComponent $livewire): void {
                            try {
                                $livewire->validateOnly($component->resolveRelativeStatePath('frame_position'));
                            } catch (ValidationException $exception) {
                                $livewire->setErrorBag($exception->validator->errors());
                            }
                        }),
                    TextInput::make('frame_position')
                        ->label(__('fieldops::resource.luminaires.fields.frame_position'))
                        ->numeric()
                        ->minValue(1)
                        ->nullable()
                        ->live(onBlur: true)
                        // Mirrors the fo_luminaires_one_active_per_position DB unique constraint
                        // (one active Luminaire per LuminairePosition) as a clean validation error
                        // instead of a raw UniqueConstraintViolationException — a duplicate frame
                        // position always means "replace", never "create another one here"
                        // (CLA-265: replacement must go through LuminaireReplacementService).
                        ->rule(function (Get $get, ?Luminaire $record): Closure {
                            return function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                if (static::hasFramePositionConflict($get('luminaire_frame_id'), $value, $record)) {
                                    $fail(__('fieldops::resource.luminaires.fields.frame_position_conflict'));
                                }
                            };
                        })
                        ->helperText(function (Get $get, ?Luminaire $record): ?string {
                            $frameId = $get('luminaire_frame_id');

                            if (blank($frameId)) {
                                return null;
                            }

                            $occupied = LuminaireFrame::find($frameId)
                                ?->luminaires()
                                ->when($record, fn (Builder $query) => $query->whereKeyNot($record->id))
                                ->pluck('frame_position')
                                ->filter()
                                ->unique()
                                ->sort()
                                ->values();

                            if (blank($occupied) || $occupied->isEmpty()) {
                                return __('fieldops::resource.luminaires.fields.frame_position_free');
                            }

                            return __('fieldops::resource.luminaires.fields.frame_position_occupied', [
                                'positions' => $occupied->implode(', '),
                            ]);
                        })
                        // Re-run only this field's validation on every change so a stale
                        // "position already in use" error clears the moment the user picks a
                        // free position, instead of only disappearing on the next form submit.
                        // See the sibling luminaire_frame_id hook above for why the exception
                        // is caught and applied manually instead of left to propagate.
                        ->afterStateUpdated(function (TextInput $component, LivewireComponent $livewire): void {
                            try {
                                $livewire->validateOnly($component->getStatePath());
                            } catch (ValidationException $exception) {
                                $livewire->setErrorBag($exception->validator->errors());
                            }
                        }),
                ])->columns(2)->columnSpanFull()
                ->visible(fn (Get $get): bool => filled($get('luminaire_type_id'))),

            Section::make(__('fieldops::resource.luminaires.sections.technical_placement'))
                ->description(__('fieldops::resource.luminaires.sections.technical_placement_description'))
                ->schema([
                    TextInput::make('frame_x')
                        ->label(__('fieldops::resource.luminaires.fields.frame_x'))
                        ->numeric()
                        ->nullable(),
                    TextInput::make('frame_y')
                        ->label(__('fieldops::resource.luminaires.fields.frame_y'))
                        ->numeric()
                        ->nullable(),
                    TextInput::make('scale_x')
                        ->label(__('fieldops::resource.luminaires.fields.scale_x'))
                        ->numeric()
                        ->step(0.01)
                        ->nullable(),
                    TextInput::make('scale_y')
                        ->label(__('fieldops::resource.luminaires.fields.scale_y'))
                        ->numeric()
                        ->step(0.01)
                        ->nullable(),
                ])->columns(2)->collapsible()->collapsed()
                ->visible(fn (Get $get): bool => filled($get('luminaire_type_id'))),

            Section::make(__('fieldops::resource.luminaires.sections.system_reference'))->schema([
                TextInput::make('cafca_material_id')
                    ->label(__('fieldops::resource.luminaires.fields.cafca_material_id'))
                    ->nullable(),
            ])->collapsible()->collapsed()
                ->visible(fn (Get $get): bool => filled($get('luminaire_type_id'))),

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
                    SpatieMediaLibraryFileUpload::make('videos')
                        ->label(__('fieldops::resource.media.videos'))
                        ->collection('videos')
                        ->multiple()
                        ->maxSize(102400)
                        ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime']),
                    SpatieMediaLibraryFileUpload::make('documents')
                        ->label(__('fieldops::resource.media.documents'))
                        ->collection('documents')
                        ->multiple()
                        ->maxSize(20480)
                        ->acceptedFileTypes(['application/pdf']),
                ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (Get $get): bool => filled($get('luminaire_type_id'))),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('operational_overview')
                ->hiddenLabel()
                ->state(fn (Luminaire $record) => static::buildOperationalOverview($record))
                ->view('fieldops::filament.infolists.luminaire-operational-overview')
                ->columnSpanFull(),

            Section::make(__('fieldops::resource.media.section_label'))
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('photos')
                        ->label(__('fieldops::resource.media.photos'))
                        ->state(fn (Luminaire $record) => $record->getMedia('photos'))
                        ->default(fn () => collect())
                        ->view('fieldops::filament.infolists.media-gallery'),
                    ViewEntry::make('videos')
                        ->label(__('fieldops::resource.media.videos'))
                        ->state(fn (Luminaire $record) => $record->getMedia('videos'))
                        ->default(fn () => collect())
                        ->view('fieldops::filament.infolists.video-gallery'),
                    ViewEntry::make('documents')
                        ->label(__('fieldops::resource.media.documents'))
                        ->state(fn (Luminaire $record) => $record->getMedia('documents'))
                        ->default(fn () => collect())
                        ->view('fieldops::filament.infolists.document-list'),
                ])
                ->collapsible()
                ->collapsed(fn (Luminaire $record) => $record->getMedia('photos')->isEmpty()
                    && $record->getMedia('videos')->isEmpty()
                    && $record->getMedia('documents')->isEmpty()),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function buildLuminaireTypeChoices(): array
    {
        return LuminaireType::query()
            ->with('subgroup')
            ->orderBy('product_family')
            ->orderBy('model_reference')
            ->get()
            ->map(fn (LuminaireType $type): array => [
                'id' => $type->id,
                'family' => $type->product_family ?: $type->name,
                'reference' => $type->model_reference,
                'application' => $type->typical_application,
                'subgroup' => $type->subgroup ? "{$type->subgroup->group_name} — {$type->subgroup->brand}" : null,
                'imageUrl' => static::resolveAssetUrl($type->image) ?: asset('assets/luminaire-subgroups/image_placeholder.png'),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildOperationalOverview(Luminaire $record): array
    {
        $record->loadMissing(['luminaireType', 'subgroup', 'luminaireFrame.frameType', 'position.installations.luminaireType']);
        $maintenanceQuery = $record->position
            ? $record->position->maintenanceRecords()
            : $record->maintenanceRecords();
        $maintenance = (clone $maintenanceQuery)
            ->with('maintenanceType')
            ->latest('maintenance_at')
            ->limit(6)
            ->get()
            ->map(fn ($maintenanceRecord): array => [
                'id' => $maintenanceRecord->id,
                'date' => $maintenanceRecord->maintenance_at?->locale(app()->getLocale())->translatedFormat('d M Y · H:i'),
                'type' => $maintenanceRecord->maintenanceType?->getTranslation('name', app()->getLocale(), false)
                    ?: $maintenanceRecord->maintenanceType?->getTranslation('name', 'nl', false),
                'status' => ($maintenanceRecord->problem_reported_at && ! $maintenanceRecord->problem_solved_at) ? 'open' : 'resolved',
                'emergency' => (bool) $maintenanceRecord->is_emergency,
                'summary' => $maintenanceRecord->problem_description ?: $maintenanceRecord->notes,
                'url' => FoMaintenanceRecordResource::getUrl('view', ['record' => $maintenanceRecord]),
            ])
            ->all();
        $workOrderQuery = FoMaintenanceWorkOrder::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->when($record->luminaire_position_id,
                fn (Builder $query, int $positionId) => $query->where('luminaire_position_id', $positionId),
                fn (Builder $query) => $query->where('maintainable_type', Luminaire::class)->where('maintainable_id', $record->id),
            );
        $workOrders = $workOrderQuery
            ->with(['maintenanceType', 'assignedEmployee'])
            ->orderBy('scheduled_for')
            ->limit(5)
            ->get()
            ->map(fn (FoMaintenanceWorkOrder $order): array => [
                'id' => $order->id,
                'status' => $order->status->getLabel(),
                'statusColor' => $order->status->getColor(),
                'type' => $order->maintenanceType?->getTranslation('name', app()->getLocale(), false)
                    ?: $order->maintenanceType?->getTranslation('name', 'nl', false),
                'scheduledFor' => $order->scheduled_for?->locale(app()->getLocale())->translatedFormat('d M Y · H:i'),
                'assignee' => $order->assignedEmployee?->name,
                'url' => FoMaintenanceWorkOrderResource::getUrl('view', ['record' => $order]),
            ])->all();

        $allMaintenance = clone $maintenanceQuery;
        $openIssues = (clone $allMaintenance)
            ->whereNotNull('problem_reported_at')
            ->whereNull('problem_solved_at')
            ->count();
        $totalMaintenance = (clone $allMaintenance)->count();
        $image = $record->luminaireType?->image;
        $frame = $record->luminaireFrame;

        return [
            'id' => $record->id,
            'serial' => $record->serial_number ?: '—',
            'productFamily' => $record->luminaireType?->product_family ?: $record->luminaireType?->name ?: __('fieldops::resource.luminaires.model_label'),
            'modelReference' => $record->luminaireType?->model_reference,
            'typicalApplication' => $record->luminaireType?->typical_application,
            'subgroup' => $record->subgroup ? "{$record->subgroup->group_name} — {$record->subgroup->brand}" : null,
            'imageUrl' => static::resolveAssetUrl($image) ?: asset('assets/luminaire-subgroups/image_placeholder.png'),
            'frameId' => $frame?->id,
            'frameTitle' => $frame ? '#'.$frame->id.' — '.($frame->frameType?->name ?: __('fieldops::resource.luminaire_frames.model_label')) : null,
            'frameImageUrl' => static::resolveAssetUrl($frame?->frameType?->image),
            'framePosition' => $record->frame_position,
            'frameX' => LuminaireFrameResource::normalizeFrameCoordinate($record->frame_x),
            'frameY' => LuminaireFrameResource::normalizeFrameCoordinate($record->frame_y),
            'scaleX' => $record->scale_x,
            'scaleY' => $record->scale_y,
            'positionSource' => $record->position_source ? __('fieldops::resource.luminaires.position_sources.'.$record->position_source) : '—',
            'positionVerifiedAt' => $record->position_verified_at?->locale(app()->getLocale())->translatedFormat('d M Y · H:i'),
            'isCurrent' => $record->removed_at === null && $record->active_position_id !== null,
            'installedAt' => $record->installed_at?->locale(app()->getLocale())->translatedFormat('d M Y · H:i'),
            'removedAt' => $record->removed_at?->locale(app()->getLocale())->translatedFormat('d M Y · H:i'),
            'installations' => $record->position?->installations
                ->sortByDesc('installed_at')
                ->map(fn (Luminaire $installation): array => [
                    'id' => $installation->id,
                    'serial' => $installation->serial_number,
                    'product' => $installation->luminaireType?->product_family ?: $installation->luminaireType?->name,
                    'installedAt' => $installation->installed_at?->locale(app()->getLocale())->translatedFormat('d M Y'),
                    'removedAt' => $installation->removed_at?->locale(app()->getLocale())->translatedFormat('d M Y'),
                    'current' => $installation->removed_at === null && $installation->active_position_id !== null,
                ])
                ->values()
                ->all() ?? [],
            'info' => $record->getTranslation('info', app()->getLocale(), false) ?: null,
            'openIssues' => $openIssues,
            'maintenanceTotal' => $totalMaintenance,
            'maintenance' => $maintenance,
            'workOrders' => $workOrders,
            'markers' => $frame ? LuminaireFrameResource::buildCanvasMarkers($frame, $record->id) : [],
            // via_structure/via_terrain: carries this page's own breadcrumb context back
            // up to the frame, so the same structure/terrain path stays consistent.
            'frameUrl' => $frame ? LuminaireFrameResource::getUrl('view', array_filter([
                'record' => $frame,
                'layout' => 'technical',
                'luminaire' => $record->id,
                'via_structure' => request()->integer('via_structure') ?: null,
                'via_terrain' => request()->integer('via_terrain') ?: null,
            ])) : null,
            'maintenanceCreateUrl' => FoMaintenanceWorkOrderResource::getUrl('create', [
                'maintainable_type' => Luminaire::class,
                'maintainable_id' => $record->id,
            ]),
            'maintenanceIndexUrl' => FoMaintenanceRecordResource::getUrl('index', [
                'luminaire' => $record->id,
                'position' => $record->luminaire_position_id,
            ]),
            'workOrderIndexUrl' => FoMaintenanceWorkOrderResource::getUrl('index'),
        ];
    }

    // Mirrors LuminaireController::resolveSerialNumber() (API/field-app path) so the
    // backoffice Create/Edit pages never violate the fo_luminaires.serial_number NOT NULL column.
    public static function resolveSerialNumber(mixed $serialNumber): string
    {
        $serial = trim((string) $serialNumber);

        if ($serial !== '') {
            return mb_substr($serial, 0, 50);
        }

        return 'AUTO-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }

    // Mirrors the fo_luminaires_one_active_per_position DB unique constraint (one
    // active Luminaire per LuminairePosition) as a clean validation error instead of
    // a raw UniqueConstraintViolationException — a duplicate frame position always
    // means "replace", never "create another one here" (CLA-265: replacement must go
    // through LuminaireReplacementService). Shared by this form's own rule() closure
    // and LuminaireFrames/RelationManagers/LuminairesRelationManager's create form —
    // the second creation surface for a Luminaire, scoped to an already-known frame.
    public static function hasFramePositionConflict(mixed $frameId, mixed $value, ?Luminaire $excluding = null): bool
    {
        if (blank($value) || blank($frameId)) {
            return false;
        }

        return Luminaire::query()
            ->current()
            ->where('luminaire_frame_id', $frameId)
            ->where('frame_position', $value)
            ->when($excluding, fn (Builder $query) => $query->whereKeyNot($excluding->id))
            ->exists();
    }

    protected static function resolveAssetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')
            ? $path
            : asset(ltrim($path, '/'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('luminaireFrame.id')
                    ->label(__('fieldops::resource.luminaires.fields.frame'))
                    ->formatStateUsing(fn ($state) => "Frame #{$state}")
                    ->sortable(),
                TextColumn::make('frame_position')
                    ->label(__('fieldops::resource.luminaires.fields.frame_position'))
                    ->sortable(),
                TextColumn::make('serial_number')
                    ->label(__('fieldops::resource.luminaires.fields.serial_number'))
                    ->searchable(),
                TextColumn::make('luminaireType.name')
                    ->label(__('fieldops::resource.luminaires.fields.luminaire_type'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('subgroup.group_name')
                    ->label(__('fieldops::resource.luminaires.fields.subgroup')),
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
            ->with(['luminaireFrame.frameType', 'luminaireType', 'subgroup'])
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLuminaires::route('/'),
            'create' => CreateLuminaire::route('/create'),
            'view' => ViewLuminaire::route('/{record}'),
            'edit' => EditLuminaire::route('/{record}/edit'),
        ];
    }
}
