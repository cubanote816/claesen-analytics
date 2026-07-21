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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\CreateLuminaire;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\EditLuminaire;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\ListLuminaires;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\ViewLuminaire;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;

class LuminaireResource extends Resource
{
    protected static ?string $model = Luminaire::class;

    protected static ?string $recordTitleAttribute = 'serial_number';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static ?int $navigationSort = 6;

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
                    ->viewData(['types' => static::buildLuminaireTypeChoices()])
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
                Select::make('luminaire_frame_id')
                    ->label(__('fieldops::resource.luminaires.fields.frame'))
                    ->options(LuminaireFrame::with('frameType')
                        ->get()
                        ->mapWithKeys(fn ($f) => [
                            $f->id => "#{$f->id} — {$f->frameType?->name}",
                        ])
                    )
                    ->searchable()
                    ->required(),
                TextInput::make('frame_position')
                    ->label(__('fieldops::resource.luminaires.fields.frame_position'))
                    ->numeric()
                    ->nullable(),
            ])->columns(2)->columnSpanFull(),

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
            ])->columns(2)->collapsible()->collapsed(),

            Section::make(__('fieldops::resource.luminaires.sections.system_reference'))->schema([
                TextInput::make('cafca_material_id')
                    ->label(__('fieldops::resource.luminaires.fields.cafca_material_id'))
                    ->nullable(),
            ])->collapsible()->collapsed(),
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
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function buildLuminaireTypeChoices(): array
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
        $record->loadMissing(['luminaireType', 'subgroup', 'luminaireFrame.frameType']);
        $maintenance = $record->maintenanceRecords()
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

        $allMaintenance = $record->maintenanceRecords();
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
            'info' => $record->getTranslation('info', app()->getLocale(), false) ?: null,
            'openIssues' => $openIssues,
            'maintenanceTotal' => $totalMaintenance,
            'maintenance' => $maintenance,
            'markers' => $frame ? LuminaireFrameResource::buildCanvasMarkers($frame, $record->id) : [],
            'frameUrl' => $frame ? LuminaireFrameResource::getUrl('view', ['record' => $frame, 'layout' => 'technical', 'luminaire' => $record->id]) : null,
            'maintenanceCreateUrl' => FoMaintenanceRecordResource::getUrl('create', [
                'maintainable_type' => Luminaire::class,
                'maintainable_id' => $record->id,
                'return_luminaire' => $record->id,
            ]),
            'maintenanceIndexUrl' => FoMaintenanceRecordResource::getUrl('index', ['luminaire' => $record->id]),
        ];
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
            'index'  => ListLuminaires::route('/'),
            'create' => CreateLuminaire::route('/create'),
            'view'   => ViewLuminaire::route('/{record}'),
            'edit'   => EditLuminaire::route('/{record}/edit'),
        ];
    }
}
