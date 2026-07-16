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
            Section::make()->schema([
                Hidden::make('position_version')
                    ->default(1),
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
                Select::make('luminaire_type_id')
                    ->label(__('fieldops::resource.luminaires.fields.luminaire_type'))
                    ->options(LuminaireType::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                Select::make('luminaire_subgroup_id')
                    ->label(__('fieldops::resource.luminaires.fields.subgroup'))
                    ->options(LuminaireSubgroup::orderBy('group_name')->get()
                        ->mapWithKeys(fn ($s) => [$s->id => "{$s->group_name} — {$s->brand}"])
                    )
                    ->searchable()
                    ->nullable(),
                TextInput::make('serial_number')
                    ->label(__('fieldops::resource.luminaires.fields.serial_number'))
                    ->nullable()
                    ->maxLength(100),
                TextInput::make('frame_position')
                    ->label(__('fieldops::resource.luminaires.fields.frame_position'))
                    ->numeric()
                    ->nullable(),
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
                TextInput::make('cafca_material_id')
                    ->label(__('fieldops::resource.luminaires.fields.cafca_material_id'))
                    ->nullable(),
            ])->columns(2),
            Section::make(__('fieldops::resource.luminaires.fields.info'))->schema([
                // Single field in the admin's current locale (app()->getLocale(),
                // set per-request by SetPanelLocale) — HasAiTranslations
                // auto-translates to the other 3 canonical locales on save.
                Textarea::make('info')
                    ->label(__('fieldops::resource.luminaires.fields.info'))
                    ->rows(2),
            ])->collapsible()->collapsed(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Group::make([
                    TextEntry::make('serial_number')
                        ->label(__('fieldops::resource.luminaires.fields.serial_number'))
                        ->placeholder('—'),
                    TextEntry::make('frame_position')
                        ->label(__('fieldops::resource.luminaires.fields.frame_position'))
                        ->placeholder('—'),
                ]),
                Group::make([
                    TextEntry::make('luminaireType.name')
                        ->label(__('fieldops::resource.luminaires.fields.luminaire_type'))
                        ->placeholder('—')
                        ->badge()
                        ->color('info'),
                    TextEntry::make('subgroup.group_name')
                        ->label(__('fieldops::resource.luminaires.fields.subgroup'))
                        ->getStateUsing(fn ($record) => $record->subgroup
                            ? "{$record->subgroup->group_name} — {$record->subgroup->brand}"
                            : null)
                        ->placeholder('—'),
                ]),
                TextEntry::make('frame_x')
                    ->label(__('fieldops::resource.luminaires.fields.frame_x').' / '.__('fieldops::resource.luminaires.fields.frame_y'))
                    ->getStateUsing(fn ($record) => \Modules\FieldOps\Filament\Resources\LuminaireFrameResource::normalizeFrameCoordinate($record->frame_x) !== null && \Modules\FieldOps\Filament\Resources\LuminaireFrameResource::normalizeFrameCoordinate($record->frame_y) !== null
                        ? sprintf(
                            'X %.2f / Y %.2f',
                            \Modules\FieldOps\Filament\Resources\LuminaireFrameResource::normalizeFrameCoordinate($record->frame_x),
                            \Modules\FieldOps\Filament\Resources\LuminaireFrameResource::normalizeFrameCoordinate($record->frame_y),
                        )
                        : '—')
                    ->badge(),
                TextEntry::make('scale_x')
                    ->label(__('fieldops::resource.luminaires.fields.scale_x').' / '.__('fieldops::resource.luminaires.fields.scale_y'))
                    ->getStateUsing(fn ($record) => ($record->scale_x ?? '—').', '.($record->scale_y ?? '—'))
                    ->badge(),
                TextEntry::make('position_source')
                    ->label(__('fieldops::resource.luminaires.fields.position_source'))
                    ->getStateUsing(fn ($record) => $record->position_source ? __('fieldops::resource.luminaires.position_sources.'.$record->position_source) : '—')
                    ->badge()
                    ->color(fn ($record) => $record->position_source === 'frontend' ? 'success' : ($record->position_source === 'backoffice' ? 'warning' : 'gray')),
                TextEntry::make('position_verified_at')
                    ->label(__('fieldops::resource.luminaires.fields.position_verified_at'))
                    ->dateTime()
                    ->placeholder('—'),
            ])->columns(2),

            Section::make()->schema([
                Group::make([
                    ViewEntry::make('recent_maintenance')
                        ->label(__('fieldops::resource.luminaires.recent_maintenance'))
                        ->state(fn (Luminaire $record) => static::buildRecentMaintenance($record))
                        ->default(fn () => [])
                        ->view('fieldops::filament.infolists.maintenance-teaser'),
                    ViewEntry::make('canvas')
                        ->label(__('fieldops::resource.luminaires.where_it_sits'))
                        ->state(fn (Luminaire $record) => $record->luminaireFrame
                            ? LuminaireFrameResource::buildCanvasMarkers($record->luminaireFrame, $record->id)
                            : [])
                        ->default(fn () => [])
                        ->view('fieldops::filament.infolists.luminaire-canvas'),
                ])->columns(2),
            ]),
        ]);
    }

    /**
     * @return array<int, array{date: ?string, type: ?string, status: string}>
     */
    protected static function buildRecentMaintenance(Luminaire $record): array
    {
        return $record->maintenanceRecords()
            ->with('maintenanceType')
            ->latest('maintenance_at')
            ->limit(2)
            ->get()
            ->map(fn ($maintenanceRecord) => [
                'date' => $maintenanceRecord->maintenance_at?->format('d M'),
                'type' => $maintenanceRecord->maintenanceType?->getTranslation('name', app()->getLocale(), false)
                    ?: $maintenanceRecord->maintenanceType?->getTranslation('name', 'nl', false),
                'status' => ($maintenanceRecord->problem_reported_at && ! $maintenanceRecord->problem_solved_at) ? 'open' : 'resolved',
            ])
            ->all();
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
