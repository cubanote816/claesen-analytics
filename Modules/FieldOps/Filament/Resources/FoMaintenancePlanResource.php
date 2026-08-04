<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources;

use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Cafca\Models\Employee;
use Modules\FieldOps\Filament\Resources\MaintenancePlans\Pages\EditMaintenancePlan;
use Modules\FieldOps\Filament\Resources\MaintenancePlans\Pages\ListMaintenancePlans;
use Modules\FieldOps\Models\FoMaintenancePlan;
use Modules\FieldOps\Models\Luminaire;

class FoMaintenancePlanResource extends Resource
{
    protected static ?string $model = FoMaintenancePlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.field_operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('fieldops::resource.maintenance_plans.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.maintenance_plans.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.maintenance_plans.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('fieldops::resource.maintenance_plans.sections.schedule'))->schema([
                Select::make('assigned_employee_id')
                    ->label(__('fieldops::resource.work_orders.fields.assignee'))
                    ->options(Employee::query()->where('fl_active', true)->orderBy('name')->get()->mapWithKeys(fn (Employee $employee) => [(string) $employee->id => $employee->name])->all())
                    ->searchable()->nullable(),
                DateTimePicker::make('next_due_at')
                    ->label(__('fieldops::resource.maintenance_plans.fields.next_due_at'))
                    ->required(),
                Select::make('recurrence_unit')
                    ->label(__('fieldops::resource.work_orders.fields.recurrence'))
                    ->options([
                        'days' => __('fieldops::resource.work_orders.recurrence.days'),
                        'weeks' => __('fieldops::resource.work_orders.recurrence.weeks'),
                        'months' => __('fieldops::resource.work_orders.recurrence.months'),
                        'years' => __('fieldops::resource.work_orders.recurrence.years'),
                    ])->required(),
                TextInput::make('recurrence_interval')
                    ->label(__('fieldops::resource.work_orders.fields.interval'))
                    ->numeric()->minValue(1)->required(),
                Textarea::make('instructions')
                    ->label(__('fieldops::resource.work_orders.fields.instructions'))
                    ->rows(4)->columnSpanFull(),
                Toggle::make('is_active')
                    ->label(__('fieldops::resource.maintenance_plans.fields.active')),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('#')->sortable(),
            IconColumn::make('is_active')->label(__('fieldops::resource.maintenance_plans.fields.active'))->boolean(),
            TextColumn::make('maintainable_type')
                ->label(__('fieldops::resource.work_orders.fields.equipment'))
                ->formatStateUsing(fn ($state, FoMaintenancePlan $record) => $state === Luminaire::class
                    ? __('fieldops::resource.luminaires.model_label').' #'.$record->maintainable_id
                    : __('fieldops::resource.electrical_boards.model_label').' #'.$record->maintainable_id),
            TextColumn::make('maintenanceType.name')
                ->label(__('fieldops::resource.maintenance_records.fields.maintenance_type'))
                ->formatStateUsing(fn ($record) => $record->maintenanceType?->getTranslation('name', app()->getLocale(), false)),
            TextColumn::make('assignedEmployee.name')->label(__('fieldops::resource.work_orders.fields.assignee'))->placeholder('—'),
            TextColumn::make('next_due_at')->label(__('fieldops::resource.maintenance_plans.fields.next_due_at'))->dateTime()->sortable(),
            TextColumn::make('recurrence_interval')
                ->label(__('fieldops::resource.work_orders.fields.recurrence'))
                ->formatStateUsing(fn ($state, FoMaintenancePlan $record) => $state.' '.__('fieldops::resource.work_orders.recurrence.'.$record->recurrence_unit)),
        ])->filters([
            TernaryFilter::make('is_active')->label(__('fieldops::resource.maintenance_plans.fields.active')),
        ])->recordActions([
            EditAction::make(),
        ])->defaultSort('next_due_at');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenancePlans::route('/'),
            'edit' => EditMaintenancePlan::route('/{record}/edit'),
        ];
    }
}
