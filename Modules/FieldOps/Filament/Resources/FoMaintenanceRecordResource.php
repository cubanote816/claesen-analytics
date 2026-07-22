<?php

namespace Modules\FieldOps\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Cafca\Models\Employee;
use Modules\FieldOps\Filament\Resources\MaintenanceRecords\Pages\CreateFoMaintenanceRecord;
use Modules\FieldOps\Filament\Resources\MaintenanceRecords\Pages\EditFoMaintenanceRecord;
use Modules\FieldOps\Filament\Resources\MaintenanceRecords\Pages\ListFoMaintenanceRecords;
use Modules\FieldOps\Filament\Resources\MaintenanceRecords\Pages\ViewFoMaintenanceRecord;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\Luminaire;

class FoMaintenanceRecordResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = FoMaintenanceRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    // FoMaintenanceRecord has no "name" column — see StructureResource::getRecordTitle()
    // for why hasRecordTitle() also needs overriding alongside this.
    public static function hasRecordTitle(): bool
    {
        return true;
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        if (! $record instanceof FoMaintenanceRecord) {
            return static::getModelLabel();
        }

        $equipment = match ($record->maintainable_type) {
            Luminaire::class => 'Luminaire',
            ElectricalBoard::class => 'Electrical board',
            default => static::getModelLabel(),
        };

        return "{$equipment} #{$record->maintainable_id} — ".$record->maintenance_at?->format('d M Y');
    }

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
        return __('fieldops::resource.maintenance_records.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.maintenance_records.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.maintenance_records.plural_label');
    }

    private static function maintainableOptions(?string $type): array
    {
        return match ($type) {
            Luminaire::class => Luminaire::query()->get()->mapWithKeys(fn ($l) => [$l->id => "Luminaire #{$l->id} ({$l->serial_number})"])->all(),
            ElectricalBoard::class => ElectricalBoard::query()->get()->mapWithKeys(fn ($b) => [$b->id => "Electrical board #{$b->id}"])->all(),
            default => [],
        };
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('maintainable_type')
                    ->label(__('fieldops::resource.maintenance_records.fields.maintainable'))
                    ->options([
                        Luminaire::class => 'Luminaire',
                        ElectricalBoard::class => 'Electrical board',
                    ])
                    ->default(fn () => request('maintainable_type'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('maintainable_id', null)),
                Select::make('maintainable_id')
                    ->label(' ')
                    ->options(fn (Get $get) => self::maintainableOptions($get('maintainable_type')))
                    ->default(fn () => request()->integer('maintainable_id') ?: null)
                    ->searchable()
                    ->required(),
                Select::make('fo_maintenance_type_id')
                    ->label(__('fieldops::resource.maintenance_records.fields.maintenance_type'))
                    ->options(fn () => FoMaintenanceType::get()->mapWithKeys(fn ($t) => [$t->id => $t->getTranslation('name', app()->getLocale(), false) ?: $t->getTranslation('name', 'nl', false)]))
                    ->searchable()
                    ->required(),
                Select::make('employee_id')
                    ->label(__('fieldops::resource.maintenance_records.fields.employee'))
                    ->options(fn () => Employee::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                Select::make('client_id')
                    ->label(__('fieldops::resource.maintenance_records.fields.client'))
                    ->options(fn () => FoClient::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                DateTimePicker::make('maintenance_at')
                    ->label(__('fieldops::resource.maintenance_records.fields.maintenance_at'))
                    ->required(),
            ])->columns(2),

            Section::make(__('fieldops::resource.maintenance_records.fields.notes'))->schema([
                Textarea::make('notes')
                    ->label(__('fieldops::resource.maintenance_records.fields.notes'))
                    ->rows(2),
            ])->collapsible(),

            Section::make('Incident / emergency')->schema([
                Toggle::make('is_emergency')
                    ->label(__('fieldops::resource.maintenance_records.fields.is_emergency')),
                Textarea::make('problem_description')
                    ->label(__('fieldops::resource.maintenance_records.fields.problem_description'))
                    ->rows(2),
                Textarea::make('root_cause')
                    ->label(__('fieldops::resource.maintenance_records.fields.root_cause'))
                    ->rows(2),
                Textarea::make('solution_applied')
                    ->label(__('fieldops::resource.maintenance_records.fields.solution_applied'))
                    ->rows(2),
                Grid::make(3)->schema([
                    DateTimePicker::make('problem_reported_at')
                        ->label(__('fieldops::resource.maintenance_records.fields.problem_reported_at')),
                    DateTimePicker::make('problem_solved_at')
                        ->label(__('fieldops::resource.maintenance_records.fields.problem_solved_at')),
                    TextInput::make('downtime_hours')
                        ->label(__('fieldops::resource.maintenance_records.fields.downtime_hours'))
                        ->numeric(),
                ]),
            ])->collapsible()->collapsed(),

            Section::make('Client-reported')->schema([
                Toggle::make('reported_by_client')
                    ->label(__('fieldops::resource.maintenance_records.fields.reported_by_client')),
                Grid::make(3)->schema([
                    Select::make('priority')
                        ->label(__('fieldops::resource.maintenance_records.fields.priority'))
                        ->options(['high' => 'High', 'medium' => 'Medium', 'low' => 'Low']),
                    TextInput::make('contact_person')
                        ->label(__('fieldops::resource.maintenance_records.fields.contact_person')),
                    TextInput::make('contact_phone')
                        ->label(__('fieldops::resource.maintenance_records.fields.contact_phone')),
                ]),
                TextInput::make('location_details')
                    ->label(__('fieldops::resource.maintenance_records.fields.location_details')),
            ])->collapsible()->collapsed(),
        ]);
    }

    /**
     * Emergency + client-reported + plain preventive/corrective all live in the
     * same table — the question that actually matters when scanning this list is
     * "is this still open?", not which columns are filled in. is_emergency wins
     * over the raw problem_status accessor so an unresolved emergency never reads
     * as a calmer "in progress".
     */
    private static function statusColor(FoMaintenanceRecord $record): string
    {
        if ($record->is_emergency && $record->problem_status !== 'resolved') {
            return 'danger';
        }

        return match ($record->problem_status) {
            'resolved' => 'success',
            'in_progress' => 'warning',
            default => 'gray',
        };
    }

    private static function statusLabel(FoMaintenanceRecord $record): string
    {
        $key = ($record->is_emergency && $record->problem_status !== 'resolved') ? 'emergency' : $record->problem_status;

        return __('fieldops::resource.maintenance_records.status.'.$key);
    }

    private static function maintainableUrl(FoMaintenanceRecord $record): ?string
    {
        return match ($record->maintainable_type) {
            Luminaire::class => LuminaireResource::getUrl('view', ['record' => $record->maintainable_id]),
            ElectricalBoard::class => ElectricalBoardResource::getUrl('view', ['record' => $record->maintainable_id]),
            default => null,
        };
    }

    private static function luminaireSummary(?Luminaire $luminaire): string
    {
        if (! $luminaire) {
            return '—';
        }

        $product = $luminaire->luminaireType?->product_family
            ?: $luminaire->luminaireType?->model_reference
            ?: $luminaire->luminaireType?->name
            ?: __('fieldops::resource.luminaires.model_label');

        return collect([$product, $luminaire->serial_number])->filter()->join(' · ');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Group::make([
                    TextEntry::make('status')
                        ->label(__('fieldops::resource.maintenance_records.status_label'))
                        ->state(fn (FoMaintenanceRecord $record) => static::statusLabel($record))
                        ->badge()
                        ->color(fn (FoMaintenanceRecord $record) => static::statusColor($record)),
                    TextEntry::make('maintenanceType.name')
                        ->label(__('fieldops::resource.maintenance_records.fields.maintenance_type'))
                        ->getStateUsing(fn ($record) => $record->maintenanceType?->getTranslation('name', app()->getLocale(), false)
                            ?: $record->maintenanceType?->getTranslation('name', 'nl', false))
                        ->badge()
                        ->color('info'),
                ]),
                TextEntry::make('maintainable_type')
                    ->label(__('fieldops::resource.maintenance_records.fields.maintainable'))
                    ->state(fn (FoMaintenanceRecord $record) => (match ($record->maintainable_type) {
                        Luminaire::class => 'Luminaire',
                        ElectricalBoard::class => 'Electrical board',
                        default => $record->maintainable_type,
                    }).' #'.$record->maintainable_id)
                    ->url(fn (FoMaintenanceRecord $record) => static::maintainableUrl($record)),
                Group::make([
                    TextEntry::make('employee.name')
                        ->label(__('fieldops::resource.maintenance_records.fields.employee'))
                        ->placeholder('—'),
                    TextEntry::make('maintenance_at')
                        ->label(__('fieldops::resource.maintenance_records.fields.maintenance_at'))
                        ->dateTime(),
                ]),
                TextEntry::make('notes')
                    ->label(__('fieldops::resource.maintenance_records.fields.notes'))
                    ->placeholder('—')
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make(__('fieldops::resource.maintenance_records.replacement_section'))
                ->description(__('fieldops::resource.maintenance_records.replacement_copy'))
                ->schema([
                    TextEntry::make('replacement_from_luminaire_id')
                        ->label(__('fieldops::resource.maintenance_records.fields.replacement_from'))
                        ->state(fn (FoMaintenanceRecord $record): string => static::luminaireSummary($record->replacementFrom))
                        ->url(fn (FoMaintenanceRecord $record): ?string => $record->replacement_from_luminaire_id
                            ? LuminaireResource::getUrl('view', ['record' => $record->replacement_from_luminaire_id])
                            : null)
                        ->icon('heroicon-m-arrow-up-right'),
                    TextEntry::make('replacement_to_luminaire_id')
                        ->label(__('fieldops::resource.maintenance_records.fields.replacement_to'))
                        ->state(fn (FoMaintenanceRecord $record): string => static::luminaireSummary($record->replacementTo))
                        ->url(fn (FoMaintenanceRecord $record): ?string => $record->replacement_to_luminaire_id
                            ? LuminaireResource::getUrl('view', ['record' => $record->replacement_to_luminaire_id])
                            : null)
                        ->icon('heroicon-m-arrow-up-right')
                        ->color('success'),
                    TextEntry::make('luminairePosition.frame_position')
                        ->label(__('fieldops::resource.maintenance_records.fields.position'))
                        ->formatStateUsing(fn ($state): string => '#'.$state),
                    TextEntry::make('replacement_reason')
                        ->label(__('fieldops::resource.maintenance_records.fields.replacement_reason'))
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn (FoMaintenanceRecord $record): bool => $record->replacement_from_luminaire_id !== null),

            Section::make(__('fieldops::resource.maintenance_records.incident_section'))
                ->schema([
                    TextEntry::make('problem_description')
                        ->label(__('fieldops::resource.maintenance_records.fields.problem_description'))
                        ->placeholder('—')
                        ->columnSpanFull(),
                    TextEntry::make('root_cause')
                        ->label(__('fieldops::resource.maintenance_records.fields.root_cause'))
                        ->placeholder('—'),
                    TextEntry::make('solution_applied')
                        ->label(__('fieldops::resource.maintenance_records.fields.solution_applied'))
                        ->placeholder('—'),
                    TextEntry::make('problem_reported_at')
                        ->label(__('fieldops::resource.maintenance_records.fields.problem_reported_at'))
                        ->dateTime()
                        ->placeholder('—'),
                    TextEntry::make('problem_solved_at')
                        ->label(__('fieldops::resource.maintenance_records.fields.problem_solved_at'))
                        ->dateTime()
                        ->placeholder('—'),
                    TextEntry::make('resolution_time_hours')
                        ->label(__('fieldops::resource.maintenance_records.fields.resolution_time_hours'))
                        ->placeholder('—'),
                ])
                ->columns(2)
                ->visible(fn (FoMaintenanceRecord $record) => filled($record->problem_reported_at)),

            Section::make(__('fieldops::resource.maintenance_records.client_reported_section'))
                ->schema([
                    TextEntry::make('client.name')
                        ->label(__('fieldops::resource.maintenance_records.fields.client'))
                        ->placeholder('—'),
                    TextEntry::make('priority')
                        ->label(__('fieldops::resource.maintenance_records.fields.priority'))
                        ->placeholder('—')
                        ->badge()
                        ->color(fn ($state) => match ($state) {
                            'high' => 'danger',
                            'medium' => 'warning',
                            default => 'gray',
                        }),
                    TextEntry::make('contact_person')
                        ->label(__('fieldops::resource.maintenance_records.fields.contact_person'))
                        ->placeholder('—'),
                    TextEntry::make('contact_phone')
                        ->label(__('fieldops::resource.maintenance_records.fields.contact_phone'))
                        ->placeholder('—'),
                    TextEntry::make('location_details')
                        ->label(__('fieldops::resource.maintenance_records.fields.location_details'))
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn (FoMaintenanceRecord $record) => $record->reported_by_client),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('status')
                    ->label(__('fieldops::resource.maintenance_records.status_label'))
                    ->state(fn (FoMaintenanceRecord $record) => static::statusLabel($record))
                    ->badge()
                    ->color(fn (FoMaintenanceRecord $record) => static::statusColor($record)),
                TextColumn::make('maintainable_type')
                    ->label(__('fieldops::resource.maintenance_records.fields.maintainable'))
                    ->formatStateUsing(fn ($state) => match ($state) {
                        Luminaire::class => 'Luminaire',
                        ElectricalBoard::class => 'Electrical board',
                        default => $state,
                    })
                    ->badge(),
                TextColumn::make('maintainable_id')->label('#'),
                TextColumn::make('maintenanceType.name')
                    ->label(__('fieldops::resource.maintenance_records.fields.maintenance_type'))
                    ->formatStateUsing(fn ($state, $record) => $record->maintenanceType?->getTranslation('name', app()->getLocale(), false)),
                TextColumn::make('maintenance_at')
                    ->label(__('fieldops::resource.maintenance_records.fields.maintenance_at'))
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('reported_by_client')
                    ->label(__('fieldops::resource.maintenance_records.fields.reported_by_client'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                TableGroup::make('maintenance_at')
                    ->date()
                    ->label(__('fieldops::resource.maintenance_records.group_by_date')),
            ])
            ->defaultGroup('maintenance_at')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('maintainable_type')
                    ->label(__('fieldops::resource.maintenance_records.fields.maintainable'))
                    ->options([
                        Luminaire::class => 'Luminaire',
                        ElectricalBoard::class => 'Electrical board',
                    ]),
                TernaryFilter::make('is_emergency')
                    ->label(__('fieldops::resource.maintenance_records.fields.is_emergency')),
                TernaryFilter::make('reported_by_client')
                    ->label(__('fieldops::resource.maintenance_records.fields.reported_by_client')),
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
            ])
            ->defaultSort('maintenance_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['maintainable', 'maintenanceType', 'employee', 'client'])
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFoMaintenanceRecords::route('/'),
            'create' => CreateFoMaintenanceRecord::route('/create'),
            'view' => ViewFoMaintenanceRecord::route('/{record}'),
            'edit' => EditFoMaintenanceRecord::route('/{record}/edit'),
        ];
    }
}
