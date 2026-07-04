<?php

namespace Modules\FieldOps\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Cafca\Models\Employee;
use Modules\FieldOps\Filament\Resources\MaintenanceRecords\Pages\CreateFoMaintenanceRecord;
use Modules\FieldOps\Filament\Resources\MaintenanceRecords\Pages\EditFoMaintenanceRecord;
use Modules\FieldOps\Filament\Resources\MaintenanceRecords\Pages\ListFoMaintenanceRecords;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\Luminaire;

class FoMaintenanceRecordResource extends Resource
{
    protected static ?string $model = FoMaintenanceRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

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
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('maintainable_id', null)),
                Select::make('maintainable_id')
                    ->label(' ')
                    ->options(fn (Get $get) => self::maintainableOptions($get('maintainable_type')))
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
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
                IconColumn::make('is_emergency')
                    ->label(__('fieldops::resource.maintenance_records.fields.is_emergency'))
                    ->boolean(),
                IconColumn::make('reported_by_client')
                    ->label(__('fieldops::resource.maintenance_records.fields.reported_by_client'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('maintainable_type')
                    ->label(__('fieldops::resource.maintenance_records.fields.maintainable'))
                    ->options([
                        Luminaire::class => 'Luminaire',
                        ElectricalBoard::class => 'Electrical board',
                    ]),
            ])
            ->recordActions([
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
            ->with(['maintainable', 'maintenanceType'])
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListFoMaintenanceRecords::route('/'),
            'create' => CreateFoMaintenanceRecord::route('/create'),
            'edit'   => EditFoMaintenanceRecord::route('/{record}/edit'),
        ];
    }
}
