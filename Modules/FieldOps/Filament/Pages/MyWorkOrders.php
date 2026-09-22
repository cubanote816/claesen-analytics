<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Services\MaintenanceEquipmentContextService;
use Modules\FieldOps\Services\MaintenanceWorkOrderService;

// CLA-581: technician-facing execution page — start/complete a work order, both actions thin
// wrappers over MaintenanceWorkOrderService::start()/submit(), the same service the Sport API
// consumes. Offline and native camera capture are explicitly out of scope (see the ticket) — this
// page only works with signal, and photos come from the device's regular file/gallery picker.
class MyWorkOrders extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 1;

    protected string $view = 'fieldops::filament.pages.my-work-orders';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('technician') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.field_operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('fieldops::resource.work_orders.my_work_orders.navigation');
    }

    public function getTitle(): string
    {
        return __('fieldops::resource.work_orders.my_work_orders.title');
    }

    public function table(Table $table): Table
    {
        $employeeId = auth()->user()?->employee_id;

        return $table
            ->query(
                FoMaintenanceWorkOrder::query()
                    // Tenant/assignment scope lives in the query itself, not just canAccess() —
                    // a technician can only ever see rows matching their own employee_id here,
                    // same rule SubmitMaintenanceWorkOrderRequest::authorize() already enforces
                    // API-side.
                    ->where('assigned_employee_id', $employeeId)
                    ->whereIn('status', [
                        MaintenanceWorkOrderStatus::PLANNED,
                        MaintenanceWorkOrderStatus::ASSIGNED,
                        MaintenanceWorkOrderStatus::IN_PROGRESS,
                    ])
                    ->orderBy('due_at')
            )
            ->columns([
                TextColumn::make('id')
                    ->label(__('fieldops::resource.work_orders.fields.order_number'))
                    ->formatStateUsing(fn (int $state): string => __('fieldops::resource.work_orders.order_number', ['id' => $state])),
                TextColumn::make('maintainable_id')
                    ->label(__('fieldops::resource.work_orders.my_work_orders.equipment'))
                    ->formatStateUsing(fn ($state, FoMaintenanceWorkOrder $record): string => app(MaintenanceEquipmentContextService::class)->equipmentLabel($record->maintainable))
                    ->description(fn (FoMaintenanceWorkOrder $record): ?string => app(MaintenanceEquipmentContextService::class)->siteLabel($record->maintainable)),
                TextColumn::make('scheduled_for')
                    ->label(__('fieldops::resource.work_orders.fields.scheduled_for'))
                    ->dateTime(),
                TextColumn::make('due_at')
                    ->label(__('fieldops::resource.work_orders.fields.due_at'))
                    ->dateTime(),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->actions([
                Action::make('start')
                    ->label(__('fieldops::resource.work_orders.my_work_orders.start'))
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (FoMaintenanceWorkOrder $record): bool => in_array($record->status, [
                        MaintenanceWorkOrderStatus::PLANNED,
                        MaintenanceWorkOrderStatus::ASSIGNED,
                    ], true))
                    ->action(function (FoMaintenanceWorkOrder $record): void {
                        $this->assertOwnRecord($record);

                        app(MaintenanceWorkOrderService::class)->start($record, auth()->id());

                        Notification::make()
                            ->title(__('fieldops::resource.work_orders.my_work_orders.started_notification'))
                            ->success()
                            ->send();
                    }),
                Action::make('complete')
                    ->label(__('fieldops::resource.work_orders.my_work_orders.complete'))
                    ->color('primary')
                    ->visible(fn (FoMaintenanceWorkOrder $record): bool => $record->status === MaintenanceWorkOrderStatus::IN_PROGRESS)
                    ->schema([
                        Wizard::make([
                            Step::make(__('fieldops::resource.work_orders.sections.tasks_performed'))
                                ->schema([
                                    Checkbox::make('completion_details.inspection')->label(__('fieldops::resource.work_orders.tasks.inspection')),
                                    Checkbox::make('completion_details.cleaning')->label(__('fieldops::resource.work_orders.tasks.cleaning')),
                                    Checkbox::make('completion_details.component_checks')->label(__('fieldops::resource.work_orders.tasks.component_checks')),
                                    Checkbox::make('completion_details.lubrication')
                                        ->label(__('fieldops::resource.work_orders.tasks.lubrication'))
                                        ->visible(fn (FoMaintenanceWorkOrder $record): bool => $record->maintainable_type === Luminaire::class),
                                    Checkbox::make('completion_details.testing')
                                        ->label(__('fieldops::resource.work_orders.tasks.testing'))
                                        ->visible(fn (FoMaintenanceWorkOrder $record): bool => $record->maintainable_type === Luminaire::class),
                                    Checkbox::make('completion_details.electrical_testing')
                                        ->label(__('fieldops::resource.work_orders.tasks.electrical_testing'))
                                        ->visible(fn (FoMaintenanceWorkOrder $record): bool => $record->maintainable_type === ElectricalBoard::class),
                                    Checkbox::make('completion_details.connection_checks')
                                        ->label(__('fieldops::resource.work_orders.tasks.connection_checks'))
                                        ->visible(fn (FoMaintenanceWorkOrder $record): bool => $record->maintainable_type === ElectricalBoard::class),
                                    Checkbox::make('completion_details.safety_verification')
                                        ->label(__('fieldops::resource.work_orders.tasks.safety_verification'))
                                        ->visible(fn (FoMaintenanceWorkOrder $record): bool => $record->maintainable_type === ElectricalBoard::class),
                                    TextInput::make('completion_details.otherTasks')
                                        ->label(__('fieldops::resource.work_orders.tasks.other_tasks'))
                                        ->columnSpanFull(),
                                ])->columns(2),
                            Step::make(__('fieldops::resource.work_orders.sections.review'))
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
                                        ->rows(3),
                                    DateTimePicker::make('completed_at')
                                        ->label(__('fieldops::resource.work_orders.my_work_orders.completed_at'))
                                        ->default(now())
                                        ->required(),
                                ]),
                            Step::make(__('fieldops::resource.work_orders.my_work_orders.photos_step'))
                                ->schema([
                                    // Plain FileUpload, not SpatieMediaLibraryFileUpload: this
                                    // form isn't bound to the maintainable model, it's bound to
                                    // the work order via the Action. Files land on a scratch
                                    // directory and get attached to $record->maintainable's own
                                    // 'photos' collection (HasFieldOpsMedia) inside the action
                                    // closure below, then removed from scratch — same disk/mime
                                    // rules the asset's collection already enforces.
                                    FileUpload::make('photos')
                                        ->label(__('fieldops::resource.work_orders.my_work_orders.photos'))
                                        ->multiple()
                                        ->image()
                                        ->disk('local')
                                        ->directory('fieldops-work-order-uploads')
                                        ->visibility('private'),
                                ]),
                        ])->submitAction(null),
                    ])
                    ->action(function (FoMaintenanceWorkOrder $record, array $data): void {
                        $this->assertOwnRecord($record);

                        $photoPaths = $data['photos'] ?? [];
                        unset($data['photos']);

                        app(MaintenanceWorkOrderService::class)->submit($record, $data, auth()->id());

                        if ($record->maintainable && filled($photoPaths)) {
                            foreach ($photoPaths as $path) {
                                $absolutePath = Storage::disk('local')->path($path);
                                if (is_file($absolutePath)) {
                                    $record->maintainable->addMedia($absolutePath)->toMediaCollection('photos');
                                }
                                Storage::disk('local')->delete($path);
                            }
                        }

                        Notification::make()
                            ->title(__('fieldops::resource.work_orders.my_work_orders.completed_notification'))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    // Defense in depth: the table query already scopes to assigned_employee_id, but the record
    // passed into an Action's closure comes back through Livewire — verify it again before any
    // state-changing call, same posture as CLA-500/CLA-501 this session.
    private function assertOwnRecord(FoMaintenanceWorkOrder $record): void
    {
        $employeeId = auth()->user()?->employee_id;

        if (! $employeeId || $record->assigned_employee_id !== $employeeId) {
            Notification::make()
                ->title(__('fieldops::resource.work_orders.my_work_orders.forbidden'))
                ->danger()
                ->send();

            throw new Halt;
        }
    }
}
