<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources;

use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Cafca\Models\Employee;
use Modules\Core\Models\User;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Filament\Resources\MaintenanceWorkOrders\Pages\CreateMaintenanceWorkOrder;
use Modules\FieldOps\Filament\Resources\MaintenanceWorkOrders\Pages\EditMaintenanceWorkOrder;
use Modules\FieldOps\Filament\Resources\MaintenanceWorkOrders\Pages\ListMaintenanceWorkOrders;
use Modules\FieldOps\Filament\Resources\MaintenanceWorkOrders\Pages\ViewMaintenanceWorkOrder;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Services\MaintenanceEquipmentContextService;

class FoMaintenanceWorkOrderResource extends Resource
{
    protected static ?string $model = FoMaintenanceWorkOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        // AWAITING_VALIDATION: the Edit page switches its form entirely to the review section
        // (root_cause/solution_applied/completion_notes/completion_details) — see form() and
        // EditMaintenanceWorkOrder::handleRecordUpdate(). Planning fields stay locked there.
        return static::canAccess() && in_array($record->status, [
            MaintenanceWorkOrderStatus::PLANNED,
            MaintenanceWorkOrderStatus::ASSIGNED,
            MaintenanceWorkOrderStatus::AWAITING_VALIDATION,
        ], true);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.field_operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('fieldops::resource.work_orders.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.work_orders.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.work_orders.plural_label');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof FoMaintenanceWorkOrder
            ? __('fieldops::resource.work_orders.order_number', ['id' => $record->id])
            : static::getModelLabel();
    }

    public static function form(Schema $schema): Schema
    {
        $type = request('maintainable_type');
        $id = request()->integer('maintainable_id') ?: null;
        $context = $type && $id ? app(MaintenanceEquipmentContextService::class)->resolve($type, $id) : null;
        $clientName = isset($context['client_id'])
            ? FoClient::query()->whereKey($context['client_id'])->value('name')
            : null;

        return $schema->components([
            Hidden::make('maintainable_type')->default($type)->required(),
            Hidden::make('maintainable_id')->default($id)->required(),
            Section::make(__('fieldops::resource.work_orders.sections.context'))
                ->description(__('fieldops::resource.work_orders.sections.context_copy'))
                ->schema([
                    TextInput::make('equipment_context')
                        ->label(__('fieldops::resource.work_orders.fields.equipment'))
                        ->default(fn (?FoMaintenanceWorkOrder $record): ?string => $record
                            ? app(MaintenanceEquipmentContextService::class)->resolve($record->maintainable_type, (int) $record->maintainable_id)['label']
                            : ($context['label'] ?? null))
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('client_context')
                        ->label(__('fieldops::resource.work_orders.fields.client'))
                        ->default(fn (?FoMaintenanceWorkOrder $record): string => $record?->client?->name
                            ?? $clientName
                            ?? __('fieldops::resource.work_orders.no_client'))
                        ->disabled()
                        ->dehydrated(false),
                ])->columns(2),
            Section::make(__('fieldops::resource.work_orders.sections.planning'))
                // awaiting_validation: locked, read-only — this page's editable fields switch
                // entirely to the review section below (updateReview() only ever touches
                // root_cause/solution_applied/completion_notes/completion_details, never these).
                ->disabled(fn (?FoMaintenanceWorkOrder $record): bool => $record?->status === MaintenanceWorkOrderStatus::AWAITING_VALIDATION)
                ->dehydrated(fn (?FoMaintenanceWorkOrder $record): bool => $record?->status !== MaintenanceWorkOrderStatus::AWAITING_VALIDATION)
                ->schema([
                Select::make('fo_maintenance_type_id')
                    ->label(__('fieldops::resource.maintenance_records.fields.maintenance_type'))
                    ->options(fn (): array => FoMaintenanceType::query()->orderBy('id')->get()->mapWithKeys(fn ($item) => [
                        $item->id => $item->getTranslation('name', app()->getLocale(), false)
                            ?: $item->getTranslation('name', 'nl', false),
                    ])->all())
                    ->required(),
                Select::make('priority')
                    ->label(__('fieldops::resource.work_orders.fields.priority'))
                    ->options([
                        'low' => __('fieldops::resource.work_orders.priority.low'),
                        'medium' => __('fieldops::resource.work_orders.priority.medium'),
                        'high' => __('fieldops::resource.work_orders.priority.high'),
                        'urgent' => __('fieldops::resource.work_orders.priority.urgent'),
                    ])->default('medium')->required(),
                Select::make('assigned_employee_id')
                    ->label(__('fieldops::resource.work_orders.fields.assignee'))
                    ->options(fn (): array => Employee::query()
                        ->where('fl_active', true)
                        ->whereIn('id', User::query()->where('is_active', true)->whereNotNull('employee_id')->select('employee_id'))
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Employee $employee) => [(string) $employee->id => $employee->name])
                        ->all())
                    ->searchable()
                    ->nullable(),
                DateTimePicker::make('scheduled_for')
                    ->label(__('fieldops::resource.work_orders.fields.scheduled_for'))
                    ->default(now()->addDay())
                    ->required(),
                DateTimePicker::make('due_at')
                    ->label(__('fieldops::resource.work_orders.fields.due_at'))
                    ->afterOrEqual('scheduled_for'),
                Textarea::make('problem_description')
                    ->label(__('fieldops::resource.maintenance_records.fields.problem_description'))
                    ->rows(3),
                Textarea::make('instructions')
                    ->label(__('fieldops::resource.work_orders.fields.instructions'))
                    ->rows(4)
                    ->columnSpanFull(),
            ])->columns(2),
            // awaiting_validation only — lets the backoffice correct/complete what the field
            // worker submitted before validating/closing (CLA-374). The task checklist mirrors
            // Claesen-Sport-updateing's TaskChecklistCard constants (deliberately not a backend
            // catalog — completion_details has no enforced shape, see
            // ExecuteMaintenanceWorkOrderRequest/SubmitMaintenanceWorkOrderRequest).
            Section::make(__('fieldops::resource.work_orders.sections.review'))
                ->description(__('fieldops::resource.work_orders.sections.review_copy'))
                ->visible(fn (?FoMaintenanceWorkOrder $record): bool => $record?->status === MaintenanceWorkOrderStatus::AWAITING_VALIDATION)
                ->schema([
                    Textarea::make('root_cause')
                        ->label(__('fieldops::resource.maintenance_records.fields.root_cause'))
                        ->rows(3),
                    Textarea::make('solution_applied')
                        ->label(__('fieldops::resource.maintenance_records.fields.solution_applied'))
                        ->rows(3)
                        ->required(),
                    Textarea::make('completion_notes')
                        ->label(__('fieldops::resource.work_orders.fields.completion_notes'))
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),
            Section::make(__('fieldops::resource.work_orders.sections.tasks_performed'))
                ->visible(fn (?FoMaintenanceWorkOrder $record): bool => $record?->status === MaintenanceWorkOrderStatus::AWAITING_VALIDATION)
                ->schema([
                    Checkbox::make('completion_details.inspection')->label(__('fieldops::resource.work_orders.tasks.inspection')),
                    Checkbox::make('completion_details.cleaning')->label(__('fieldops::resource.work_orders.tasks.cleaning')),
                    Checkbox::make('completion_details.component_checks')->label(__('fieldops::resource.work_orders.tasks.component_checks')),
                    Checkbox::make('completion_details.lubrication')
                        ->label(__('fieldops::resource.work_orders.tasks.lubrication'))
                        ->visible(fn (?FoMaintenanceWorkOrder $record): bool => $record?->maintainable_type === Luminaire::class),
                    Checkbox::make('completion_details.testing')
                        ->label(__('fieldops::resource.work_orders.tasks.testing'))
                        ->visible(fn (?FoMaintenanceWorkOrder $record): bool => $record?->maintainable_type === Luminaire::class),
                    Checkbox::make('completion_details.electrical_testing')
                        ->label(__('fieldops::resource.work_orders.tasks.electrical_testing'))
                        ->visible(fn (?FoMaintenanceWorkOrder $record): bool => $record?->maintainable_type === ElectricalBoard::class),
                    Checkbox::make('completion_details.connection_checks')
                        ->label(__('fieldops::resource.work_orders.tasks.connection_checks'))
                        ->visible(fn (?FoMaintenanceWorkOrder $record): bool => $record?->maintainable_type === ElectricalBoard::class),
                    Checkbox::make('completion_details.safety_verification')
                        ->label(__('fieldops::resource.work_orders.tasks.safety_verification'))
                        ->visible(fn (?FoMaintenanceWorkOrder $record): bool => $record?->maintainable_type === ElectricalBoard::class),
                    TextInput::make('completion_details.otherTasks')
                        ->label(__('fieldops::resource.work_orders.tasks.other_tasks'))
                        ->columnSpanFull(),
                ])->columns(3),
            Section::make(__('fieldops::resource.work_orders.sections.recurrence'))
                ->description(__('fieldops::resource.work_orders.sections.recurrence_copy'))
                ->visible(fn (?FoMaintenanceWorkOrder $record): bool => $record === null)
                ->schema([
                    Select::make('recurrence_unit')
                        ->label(__('fieldops::resource.work_orders.fields.recurrence'))
                        ->options([
                            'days' => __('fieldops::resource.work_orders.recurrence.days'),
                            'weeks' => __('fieldops::resource.work_orders.recurrence.weeks'),
                            'months' => __('fieldops::resource.work_orders.recurrence.months'),
                            'years' => __('fieldops::resource.work_orders.recurrence.years'),
                        ])->live()->nullable(),
                    TextInput::make('recurrence_interval')
                        ->label(__('fieldops::resource.work_orders.fields.interval'))
                        ->numeric()->minValue(1)->default(1),
                ])->columns(2)->collapsible(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('fieldops::resource.work_orders.sections.summary'))->schema([
                TextEntry::make('status')->label(__('fieldops::resource.work_orders.fields.status'))->badge(),
                TextEntry::make('maintenanceType.name')
                    ->label(__('fieldops::resource.maintenance_records.fields.maintenance_type'))
                    ->formatStateUsing(fn ($record) => $record->maintenanceType?->getTranslation('name', app()->getLocale(), false)),
                TextEntry::make('scheduled_for')->label(__('fieldops::resource.work_orders.fields.scheduled_for'))->dateTime(),
                TextEntry::make('due_at')->label(__('fieldops::resource.work_orders.fields.due_at'))->dateTime()->placeholder('—'),
                TextEntry::make('assignedEmployee.name')->label(__('fieldops::resource.work_orders.fields.assignee'))->placeholder('—'),
                TextEntry::make('client.name')->label(__('fieldops::resource.work_orders.fields.client'))->placeholder('—'),
                TextEntry::make('problem_description')->label(__('fieldops::resource.maintenance_records.fields.problem_description'))->placeholder('—')->columnSpanFull(),
                TextEntry::make('instructions')->label(__('fieldops::resource.work_orders.fields.instructions'))->placeholder('—')->columnSpanFull(),
            ])->columns(2),
            Section::make(__('fieldops::resource.work_orders.sections.execution'))->schema([
                TextEntry::make('started_at')->label(__('fieldops::resource.work_orders.fields.started_at'))->dateTime()->placeholder('—'),
                TextEntry::make('submitted_at')->label(__('fieldops::resource.work_orders.fields.submitted_at'))->dateTime()->placeholder('—'),
                TextEntry::make('root_cause')->label(__('fieldops::resource.maintenance_records.fields.root_cause'))->placeholder('—'),
                TextEntry::make('solution_applied')->label(__('fieldops::resource.maintenance_records.fields.solution_applied'))->placeholder('—'),
                TextEntry::make('completion_notes')->label(__('fieldops::resource.work_orders.fields.completion_notes'))->placeholder('—')->columnSpanFull(),
                TextEntry::make('completion_details')
                    ->label(__('fieldops::resource.work_orders.sections.tasks_performed'))
                    ->placeholder('—')
                    ->columnSpanFull()
                    ->formatStateUsing(function (?array $state): ?string {
                        if (! $state) {
                            return null;
                        }
                        $labels = collect($state)
                            ->filter(fn ($value, $key) => $key !== 'otherTasks' && $value === true)
                            ->keys()
                            ->map(fn ($key) => __("fieldops::resource.work_orders.tasks.{$key}"))
                            ->all();
                        if (! empty($state['otherTasks'])) {
                            $labels[] = $state['otherTasks'];
                        }

                        return $labels ? implode(', ', $labels) : null;
                    }),
                TextEntry::make('override_reason')->label(__('fieldops::resource.work_orders.fields.override_reason'))->placeholder('—')->columnSpanFull(),
            ])->columns(2)->collapsible(),
            Section::make(__('fieldops::resource.work_orders.sections.assignment'))->schema([
                TextEntry::make('assignedBy.name')->label(__('fieldops::resource.work_orders.fields.assigned_by'))->placeholder('—'),
                TextEntry::make('assigned_at')->label(__('fieldops::resource.work_orders.fields.assigned_at'))->dateTime()->placeholder('—'),
                TextEntry::make('returnedBy.name')->label(__('fieldops::resource.work_orders.fields.returned_by'))->placeholder('—'),
                TextEntry::make('returned_at')->label(__('fieldops::resource.work_orders.fields.returned_at'))->dateTime()->placeholder('—'),
                TextEntry::make('return_reason')->label(__('fieldops::resource.work_orders.fields.return_reason'))->placeholder('—')->columnSpanFull(),
            ])->columns(2)->collapsible(),
            Section::make(__('fieldops::resource.work_orders.sections.timeline'))->schema([
                RepeatableEntry::make('events')
                    ->hiddenLabel()
                    ->schema([
                        TextEntry::make('event_type')
                            ->label(__('fieldops::resource.work_orders.fields.event'))
                            ->badge()
                            ->formatStateUsing(fn ($state): string => __(sprintf(
                                'fieldops::resource.work_orders.events.%s',
                                $state instanceof BackedEnum ? $state->value : $state,
                            ))),
                        TextEntry::make('actor.name')->label(__('fieldops::resource.work_orders.fields.actor'))->placeholder('—'),
                        TextEntry::make('occurred_at')->label(__('fieldops::resource.work_orders.fields.occurred_at'))->dateTime(),
                        TextEntry::make('from_status')->label(__('fieldops::resource.work_orders.fields.from_status'))->placeholder('—'),
                        TextEntry::make('to_status')->label(__('fieldops::resource.work_orders.fields.to_status'))->placeholder('—'),
                        TextEntry::make('data.reason')->label(__('fieldops::resource.work_orders.fields.reason'))->placeholder('—')->columnSpanFull(),
                    ])
                    ->columns(3),
            ])->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('#')->sortable(),
            TextColumn::make('status')->label(__('fieldops::resource.work_orders.fields.status'))->badge()->sortable(),
            ImageColumn::make('equipment_image')
                ->label('')
                ->getStateUsing(function (FoMaintenanceWorkOrder $record): ?string {
                    $equipment = $record->maintainable;
                    if (! $equipment instanceof Luminaire) {
                        return null;
                    }

                    $image = $equipment->luminaireType?->image;

                    return $image
                        ? (str_starts_with($image, 'http') ? $image : asset(ltrim($image, '/')))
                        : asset('assets/luminaire-subgroups/image_placeholder.png');
                })
                ->square()
                ->size(44),
            TextColumn::make('maintainable_id')
                ->label(__('fieldops::resource.work_orders.fields.equipment'))
                ->formatStateUsing(fn ($state, FoMaintenanceWorkOrder $record): string => app(MaintenanceEquipmentContextService::class)->equipmentLabel($record->maintainable))
                ->description(fn (FoMaintenanceWorkOrder $record): ?string => app(MaintenanceEquipmentContextService::class)->siteLabel($record->maintainable))
                ->searchable(),
            TextColumn::make('client.name')->label(__('fieldops::resource.work_orders.fields.client'))->placeholder('—')->searchable(),
            TextColumn::make('maintenanceType.name')
                ->label(__('fieldops::resource.maintenance_records.fields.maintenance_type'))
                ->formatStateUsing(fn ($record) => $record->maintenanceType?->getTranslation('name', app()->getLocale(), false))
                ->badge(),
            TextColumn::make('scheduled_for')->label(__('fieldops::resource.work_orders.fields.scheduled_for'))->dateTime()->sortable(),
            TextColumn::make('assignedEmployee.name')->label(__('fieldops::resource.work_orders.fields.assignee'))->placeholder('—')->searchable(),
            TextColumn::make('priority')->label(__('fieldops::resource.work_orders.fields.priority'))->badge(),
        ])->filters([
            SelectFilter::make('status')->options(collect(MaintenanceWorkOrderStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->getLabel()])->all()),
            SelectFilter::make('assigned_employee_id')->label(__('fieldops::resource.work_orders.fields.assignee'))->options(Employee::query()->orderBy('name')->get()->mapWithKeys(fn (Employee $employee) => [(string) $employee->id => $employee->name])->all()),
        ])->recordActions([
            ViewAction::make(),
            EditAction::make()->visible(fn (FoMaintenanceWorkOrder $record): bool => static::canEdit($record)),
        ])->defaultSort('scheduled_for');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceWorkOrders::route('/'),
            'create' => CreateMaintenanceWorkOrder::route('/create'),
            'view' => ViewMaintenanceWorkOrder::route('/{record}'),
            'edit' => EditMaintenanceWorkOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with([
            'maintainable',
            'maintenanceType',
            'client',
            'assignedEmployee',
            'assignedBy',
            'returnedBy',
            'events.actor',
        ]);
    }
}
