<?php

declare(strict_types=1);

namespace Modules\FieldOps\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\FieldOps\Enums\MaintenanceWorkOrderEventType;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Models\FoMaintenancePlan;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Notifications\ClientRequestNotification;

class MaintenanceWorkOrderService
{
    public function __construct(
        private readonly MaintenanceEquipmentContextService $context,
        private readonly WorkOrderNotificationService $notifications,
    ) {}

    public function create(array $data, ?int $userId): FoMaintenanceWorkOrder
    {
        // Filament's Select stores the chosen employee id as an options() array
        // key, and PHP unconditionally coerces numeric-looking string array keys
        // (e.g. legacy employee id "100") to int — so $data may carry an int here
        // even though employees.id is a string column. Normalize at the boundary.
        $assignedEmployeeId = isset($data['assigned_employee_id']) ? (string) $data['assigned_employee_id'] : null;
        $this->assertAssignableEmployee($assignedEmployeeId);

        $order = DB::transaction(function () use ($data, $userId, $assignedEmployeeId): FoMaintenanceWorkOrder {
            $context = $this->context->resolve($data['maintainable_type'], (int) $data['maintainable_id']);
            $recurrenceUnit = $data['recurrence_unit'] ?? null;
            $recurrenceInterval = (int) ($data['recurrence_interval'] ?? 1);
            unset($data['recurrence_unit'], $data['recurrence_interval']);

            $data = array_merge($data, [
                'created_by_user_id' => $userId,
                'client_id' => $context['client_id'],
                'luminaire_position_id' => $context['luminaire_position_id'],
                'assigned_employee_id' => $assignedEmployeeId,
                'assigned_by_user_id' => $assignedEmployeeId ? $userId : null,
                'assigned_at' => $assignedEmployeeId ? now() : null,
                'status' => $assignedEmployeeId
                    ? MaintenanceWorkOrderStatus::ASSIGNED
                    : MaintenanceWorkOrderStatus::PLANNED,
                'source' => $data['source'] ?? 'backoffice',
            ]);

            $order = FoMaintenanceWorkOrder::query()->create($data);
            $this->recordEvent(
                $order,
                MaintenanceWorkOrderEventType::CREATED,
                $userId,
                null,
                MaintenanceWorkOrderStatus::PLANNED,
                null,
                $assignedEmployeeId,
            );

            if ($assignedEmployeeId) {
                $this->recordEvent(
                    $order,
                    MaintenanceWorkOrderEventType::ASSIGNED,
                    $userId,
                    MaintenanceWorkOrderStatus::PLANNED,
                    MaintenanceWorkOrderStatus::ASSIGNED,
                    null,
                    $assignedEmployeeId,
                );
            }

            if ($recurrenceUnit) {
                $nextDue = $this->advance(Carbon::parse($order->scheduled_for), $recurrenceUnit, $recurrenceInterval);
                $plan = FoMaintenancePlan::query()->create([
                    'created_by_user_id' => $userId,
                    'fo_maintenance_type_id' => $order->fo_maintenance_type_id,
                    'maintainable_type' => $order->maintainable_type,
                    'maintainable_id' => $order->maintainable_id,
                    'luminaire_position_id' => $order->luminaire_position_id,
                    'client_id' => $order->client_id,
                    'assigned_employee_id' => $order->assigned_employee_id,
                    'recurrence_unit' => $recurrenceUnit,
                    'recurrence_interval' => $recurrenceInterval,
                    'next_due_at' => $nextDue,
                    'instructions' => $order->instructions,
                    'is_active' => true,
                ]);
                $order->update(['maintenance_plan_id' => $plan->id]);
            }

            return $order->fresh(['assignedBy']);
        });

        if ($assignedEmployeeId) {
            $this->notifications->assignmentChanged($order, null, $assignedEmployeeId);
        }

        return $order;
    }

    public function updatePlanning(FoMaintenanceWorkOrder $order, array $data, int $userId): FoMaintenanceWorkOrder
    {
        // See create() above: normalize the Select-derived employee id back to
        // string before it reaches strictly-typed code or gets compared/persisted.
        $newEmployeeId = isset($data['assigned_employee_id']) ? (string) $data['assigned_employee_id'] : null;
        $this->assertAssignableEmployee($newEmployeeId);

        [$updated, $previousEmployeeId] = DB::transaction(function () use ($order, $data, $userId, $newEmployeeId): array {
            $locked = FoMaintenanceWorkOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($locked->status, [MaintenanceWorkOrderStatus::PLANNED, MaintenanceWorkOrderStatus::ASSIGNED], true)) {
                throw ValidationException::withMessages([
                    'status' => __('fieldops::resource.work_orders.validation.cannot_edit'),
                ]);
            }

            $previousEmployeeId = $locked->assigned_employee_id;
            $previousStatus = $locked->status;
            $assignmentChanged = $previousEmployeeId !== $newEmployeeId;
            $nextStatus = $newEmployeeId
                ? MaintenanceWorkOrderStatus::ASSIGNED
                : MaintenanceWorkOrderStatus::PLANNED;
            $changes = Arr::only($data, [
                'fo_maintenance_type_id', 'assigned_employee_id', 'scheduled_for', 'due_at',
                'priority', 'problem_description', 'instructions',
            ]);
            $changes['assigned_employee_id'] = $newEmployeeId;
            $changes['status'] = $nextStatus;

            if ($assignmentChanged) {
                $changes['assigned_by_user_id'] = $newEmployeeId ? $userId : null;
                $changes['assigned_at'] = $newEmployeeId ? now() : null;
            }

            $locked->update($changes);

            if ($assignmentChanged) {
                $event = match (true) {
                    $previousEmployeeId === null => MaintenanceWorkOrderEventType::ASSIGNED,
                    $newEmployeeId === null => MaintenanceWorkOrderEventType::UNASSIGNED,
                    default => MaintenanceWorkOrderEventType::REASSIGNED,
                };
                $this->recordEvent(
                    $locked,
                    $event,
                    $userId,
                    $previousStatus,
                    $nextStatus,
                    $previousEmployeeId,
                    $newEmployeeId,
                );
            }

            return [$locked->fresh(['assignedBy']), $previousEmployeeId];
        });

        if ($previousEmployeeId !== $newEmployeeId) {
            $this->notifications->assignmentChanged($updated, $previousEmployeeId, $newEmployeeId);
        }

        return $updated;
    }

    public function generateDueOrders(bool $dryRun = false): int
    {
        $plans = FoMaintenancePlan::query()
            ->where('is_active', true)
            ->where('next_due_at', '<=', now())
            ->orderBy('next_due_at')
            ->get();

        if ($dryRun) {
            return $plans->count();
        }

        $createdOrders = [];

        foreach ($plans as $plan) {
            $created = DB::transaction(function () use ($plan): ?FoMaintenanceWorkOrder {
                $locked = FoMaintenancePlan::query()->lockForUpdate()->findOrFail($plan->id);
                if (! $locked->is_active || $locked->next_due_at->isFuture()) {
                    return null;
                }

                $assignedEmployeeId = $this->notifications->activeUserForEmployee($locked->assigned_employee_id)
                    ? $locked->assigned_employee_id
                    : null;
                $order = FoMaintenanceWorkOrder::query()->create([
                    'created_by_user_id' => $locked->created_by_user_id,
                    'maintenance_plan_id' => $locked->id,
                    'fo_maintenance_type_id' => $locked->fo_maintenance_type_id,
                    'maintainable_type' => $locked->maintainable_type,
                    'maintainable_id' => $locked->maintainable_id,
                    'luminaire_position_id' => $locked->luminaire_position_id,
                    'client_id' => $locked->client_id,
                    'assigned_employee_id' => $assignedEmployeeId,
                    'assigned_by_user_id' => $assignedEmployeeId ? $locked->created_by_user_id : null,
                    'assigned_at' => $assignedEmployeeId ? now() : null,
                    'status' => $assignedEmployeeId ? MaintenanceWorkOrderStatus::ASSIGNED : MaintenanceWorkOrderStatus::PLANNED,
                    'priority' => 'medium',
                    'source' => 'maintenance_plan',
                    'scheduled_for' => $locked->next_due_at,
                    'instructions' => $locked->instructions,
                ]);

                $locked->update([
                    'next_due_at' => $this->advance($locked->next_due_at, $locked->recurrence_unit, $locked->recurrence_interval),
                ]);

                $this->recordEvent(
                    $order,
                    MaintenanceWorkOrderEventType::CREATED,
                    $locked->created_by_user_id,
                    null,
                    MaintenanceWorkOrderStatus::PLANNED,
                    null,
                    $assignedEmployeeId,
                    ['maintenance_plan_id' => $locked->id],
                );

                if ($assignedEmployeeId) {
                    $this->recordEvent(
                        $order,
                        MaintenanceWorkOrderEventType::ASSIGNED,
                        $locked->created_by_user_id,
                        MaintenanceWorkOrderStatus::PLANNED,
                        MaintenanceWorkOrderStatus::ASSIGNED,
                        null,
                        $assignedEmployeeId,
                    );
                }

                return $order->fresh(['assignedBy']);
            });

            if ($created) {
                $createdOrders[] = $created;
            }
        }

        foreach ($createdOrders as $createdOrder) {
            if ($createdOrder->assigned_employee_id) {
                $this->notifications->assignmentChanged($createdOrder, null, $createdOrder->assigned_employee_id);
            }
        }

        return $plans->count();
    }

    public function start(FoMaintenanceWorkOrder $order, int $userId): FoMaintenanceWorkOrder
    {
        return DB::transaction(function () use ($order, $userId): FoMaintenanceWorkOrder {
            $order = FoMaintenanceWorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (! in_array($order->status, [MaintenanceWorkOrderStatus::PLANNED, MaintenanceWorkOrderStatus::ASSIGNED], true)) {
                throw ValidationException::withMessages(['status' => __('fieldops::resource.work_orders.validation.cannot_start')]);
            }

            $fromStatus = $order->status;
            $order->update([
                'status' => MaintenanceWorkOrderStatus::IN_PROGRESS,
                'started_at' => now(),
                'started_by_user_id' => $userId,
            ]);
            $this->recordEvent($order, MaintenanceWorkOrderEventType::STARTED, $userId, $fromStatus, MaintenanceWorkOrderStatus::IN_PROGRESS);
            app(MaintenanceRequestService::class)->markInProgress($order);

            return $order->fresh();
        });
    }

    public function submit(FoMaintenanceWorkOrder $order, array $data, int $userId): FoMaintenanceWorkOrder
    {
        $order = DB::transaction(function () use ($order, $data, $userId): FoMaintenanceWorkOrder {
            $order = FoMaintenanceWorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== MaintenanceWorkOrderStatus::IN_PROGRESS) {
                throw ValidationException::withMessages(['status' => __('fieldops::resource.work_orders.validation.cannot_submit')]);
            }

            $completedAt = Carbon::parse($data['completed_at'] ?? now());
            if ($order->started_at && $completedAt->lt($order->started_at)) {
                throw ValidationException::withMessages(['completed_at' => __('fieldops::resource.work_orders.validation.completion_before_start')]);
            }

            $order->update(array_merge($data, [
                'status' => MaintenanceWorkOrderStatus::AWAITING_VALIDATION,
                'submitted_at' => now(),
                'completed_at' => $completedAt,
                'completed_by_user_id' => $userId,
            ]));
            $this->recordEvent($order, MaintenanceWorkOrderEventType::SUBMITTED, $userId, MaintenanceWorkOrderStatus::IN_PROGRESS, MaintenanceWorkOrderStatus::AWAITING_VALIDATION);

            return $order->fresh(['assignedBy']);
        });

        $this->notifications->submitted($order);

        return $order;
    }

    public function returnForCorrection(FoMaintenanceWorkOrder $order, int $userId, string $reason): FoMaintenanceWorkOrder
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['return_reason' => __('fieldops::resource.work_orders.validation.return_reason')]);
        }

        $order = DB::transaction(function () use ($order, $userId, $reason): FoMaintenanceWorkOrder {
            $order = FoMaintenanceWorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== MaintenanceWorkOrderStatus::AWAITING_VALIDATION) {
                throw ValidationException::withMessages(['status' => __('fieldops::resource.work_orders.validation.cannot_return')]);
            }

            $order->update([
                'status' => MaintenanceWorkOrderStatus::IN_PROGRESS,
                'returned_at' => now(),
                'returned_by_user_id' => $userId,
                'return_reason' => $reason,
            ]);
            $this->recordEvent(
                $order,
                MaintenanceWorkOrderEventType::RETURNED,
                $userId,
                MaintenanceWorkOrderStatus::AWAITING_VALIDATION,
                MaintenanceWorkOrderStatus::IN_PROGRESS,
                data: ['reason' => $reason],
            );

            return $order->fresh(['assignedBy']);
        });

        $this->notifications->returned($order, $reason);

        return $order;
    }

    public function close(FoMaintenanceWorkOrder $order, int $userId, ?string $overrideReason = null): FoMaintenanceWorkOrder
    {
        $closed = DB::transaction(fn () => $this->closeCore($order, $userId, $overrideReason));

        $this->notifications->completed($closed);
        if ($closed->serviceRequest?->reporter) {
            $closed->serviceRequest->reporter->notify(new ClientRequestNotification($closed->serviceRequest, 'resolved'));
        }

        return $closed;
    }

    /**
     * Creates a work order self-assigned to the requester's own employee record and closes it in
     * the same transaction — no separate start/submit/validate actors. Used by the "execute now"
     * endpoint (a project_manager/admin who fixes something on the spot and registers it
     * immediately, as opposed to assigning it to a technician for later execution via create()).
     *
     * Logs the full CREATED→ASSIGNED→STARTED→SUBMITTED→OVERRIDDEN event chain at the same instant
     * so the audit trail reads the same shape as a normal order's history, just compressed in
     * time — no new event type needed. No notifications fire: the executor is the assigner, the
     * assignee, and the closer all at once, so "assignment changed"/"completed" would just notify
     * themselves about their own action.
     */
    public function createAndClose(array $data, int $userId, string $employeeId): FoMaintenanceWorkOrder
    {
        return DB::transaction(function () use ($data, $userId, $employeeId): FoMaintenanceWorkOrder {
            $context = $this->context->resolve($data['maintainable_type'], (int) $data['maintainable_id']);
            $now = now();

            $order = FoMaintenanceWorkOrder::query()->create([
                'created_by_user_id' => $userId,
                'client_id' => $context['client_id'],
                'luminaire_position_id' => $context['luminaire_position_id'],
                'maintainable_type' => $data['maintainable_type'],
                'maintainable_id' => $data['maintainable_id'],
                'fo_maintenance_type_id' => $data['fo_maintenance_type_id'],
                'assigned_employee_id' => $employeeId,
                'assigned_by_user_id' => $userId,
                'assigned_at' => $now,
                'status' => MaintenanceWorkOrderStatus::IN_PROGRESS,
                'priority' => $data['priority'],
                'scheduled_for' => $now,
                'problem_description' => $data['problem_description'] ?? null,
                'root_cause' => $data['root_cause'] ?? null,
                'solution_applied' => $data['solution_applied'],
                'completion_notes' => $data['completion_notes'] ?? null,
                'completion_details' => $data['completion_details'] ?? null,
                'started_at' => $now,
                'started_by_user_id' => $userId,
                'submitted_at' => $now,
                'completed_at' => $now,
                'completed_by_user_id' => $userId,
                'source' => 'field_self_execution',
            ]);

            $this->recordEvent($order, MaintenanceWorkOrderEventType::CREATED, $userId, null, MaintenanceWorkOrderStatus::PLANNED, null, $employeeId);
            $this->recordEvent($order, MaintenanceWorkOrderEventType::ASSIGNED, $userId, MaintenanceWorkOrderStatus::PLANNED, MaintenanceWorkOrderStatus::ASSIGNED, null, $employeeId);
            $this->recordEvent($order, MaintenanceWorkOrderEventType::STARTED, $userId, MaintenanceWorkOrderStatus::ASSIGNED, MaintenanceWorkOrderStatus::IN_PROGRESS);
            // Logged for a complete audit narrative even though the order's live status never
            // actually sits in AWAITING_VALIDATION — it goes IN_PROGRESS straight to COMPLETED via
            // closeCore()'s override branch below, since there's no separate reviewer here.
            $this->recordEvent($order, MaintenanceWorkOrderEventType::SUBMITTED, $userId, MaintenanceWorkOrderStatus::IN_PROGRESS, MaintenanceWorkOrderStatus::AWAITING_VALIDATION);

            // Intentionally left at IN_PROGRESS (not AWAITING_VALIDATION) so closeCore() takes its
            // override branch — creator and closer being the same person, with no separate
            // submission/validation actors, is exactly what "override" means here.
            return $this->closeCore(
                $order->fresh(),
                $userId,
                __('fieldops::resource.work_orders.self_execution_reason'),
            );
        });
    }

    private function closeCore(FoMaintenanceWorkOrder $order, int $userId, ?string $overrideReason): FoMaintenanceWorkOrder
    {
        $order = FoMaintenanceWorkOrder::query()->lockForUpdate()->findOrFail($order->id);
        $override = $order->status !== MaintenanceWorkOrderStatus::AWAITING_VALIDATION;

        if ($order->status === MaintenanceWorkOrderStatus::COMPLETED || $order->maintenance_record_id) {
            throw ValidationException::withMessages(['status' => __('fieldops::resource.work_orders.validation.already_closed')]);
        }
        if ($order->status === MaintenanceWorkOrderStatus::CANCELLED) {
            throw ValidationException::withMessages(['status' => __('fieldops::resource.work_orders.validation.cancelled')]);
        }
        if ($override && blank($overrideReason)) {
            throw ValidationException::withMessages(['override_reason' => __('fieldops::resource.work_orders.validation.override_reason')]);
        }

        $fromStatus = $order->status;
        $completedAt = $order->completed_at ?? now();
        $type = FoMaintenanceType::query()->findOrFail($order->fo_maintenance_type_id);
        $details = $order->completion_details ?? [];
        if ($override) {
            $details['backoffice_override'] = true;
            $details['override_reason'] = $overrideReason;
        }
        $record = $order->maintainable->maintenanceRecords()->create([
            'created_by_user_id' => $userId,
            'fo_maintenance_type_id' => $order->fo_maintenance_type_id,
            'luminaire_position_id' => $order->luminaire_position_id,
            'employee_id' => $order->assigned_employee_id,
            'client_id' => $order->client_id,
            'maintenance_at' => $completedAt,
            'details' => $details ?: null,
            'notes' => $order->completion_notes,
            'problem_description' => $order->problem_description,
            'root_cause' => $order->root_cause,
            'solution_applied' => $order->solution_applied,
            'is_emergency' => $type->code === FoMaintenanceType::CODE_EMERGENCY,
            'problem_reported_at' => $order->problem_description ? $order->created_at : null,
            'problem_solved_at' => $order->problem_description ? $completedAt : null,
            'downtime_hours' => $order->problem_description ? $order->created_at->diffInMinutes($completedAt, true) / 60 : null,
            'priority' => $order->priority,
        ]);

        $order->update([
            'status' => MaintenanceWorkOrderStatus::COMPLETED,
            'completed_at' => $completedAt,
            'validated_at' => now(),
            'validated_by_user_id' => $userId,
            'override_reason' => $override ? $overrideReason : null,
            'maintenance_record_id' => $record->id,
        ]);
        app(MaintenanceRequestService::class)->resolveFromWorkOrder(
            $order,
            $order->solution_applied ?: $order->completion_notes ?: 'The maintenance work has been completed.',
        );
        $this->recordEvent(
            $order,
            $override ? MaintenanceWorkOrderEventType::OVERRIDDEN : MaintenanceWorkOrderEventType::VALIDATED,
            $userId,
            $fromStatus,
            MaintenanceWorkOrderStatus::COMPLETED,
            data: $override ? ['reason' => $overrideReason] : null,
        );

        return $order->fresh(['maintenanceRecord', 'assignedBy', 'serviceRequest.reporter']);
    }

    public function cancel(FoMaintenanceWorkOrder $order, int $userId, string $reason): FoMaintenanceWorkOrder
    {
        $order = DB::transaction(function () use ($order, $userId, $reason): FoMaintenanceWorkOrder {
            $order = FoMaintenanceWorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (in_array($order->status, [MaintenanceWorkOrderStatus::COMPLETED, MaintenanceWorkOrderStatus::CANCELLED], true)) {
                throw ValidationException::withMessages(['status' => __('fieldops::resource.work_orders.validation.cannot_cancel')]);
            }

            $fromStatus = $order->status;
            $order->update([
                'status' => MaintenanceWorkOrderStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $userId,
                'cancellation_reason' => $reason,
            ]);
            app(MaintenanceRequestService::class)->rejectFromWorkOrder($order, $reason);
            $this->recordEvent(
                $order,
                MaintenanceWorkOrderEventType::CANCELLED,
                $userId,
                $fromStatus,
                MaintenanceWorkOrderStatus::CANCELLED,
                data: ['reason' => $reason],
            );

            return $order->fresh(['assignedBy', 'serviceRequest.reporter']);
        });

        $this->notifications->cancelled($order, $reason);
        if ($order->serviceRequest?->reporter) {
            $order->serviceRequest->reporter->notify(new ClientRequestNotification($order->serviceRequest, 'updated'));
        }

        return $order;
    }

    private function assertAssignableEmployee(?string $employeeId): void
    {
        if ($employeeId && ! $this->notifications->activeUserForEmployee($employeeId)) {
            throw ValidationException::withMessages([
                'assigned_employee_id' => __('fieldops::resource.work_orders.validation.assignee_requires_user'),
            ]);
        }
    }

    private function recordEvent(
        FoMaintenanceWorkOrder $order,
        MaintenanceWorkOrderEventType $event,
        ?int $actorUserId,
        MaintenanceWorkOrderStatus|string|null $fromStatus = null,
        MaintenanceWorkOrderStatus|string|null $toStatus = null,
        ?string $fromEmployeeId = null,
        ?string $toEmployeeId = null,
        ?array $data = null,
    ): void {
        $order->events()->create([
            'event_type' => $event,
            'actor_user_id' => $actorUserId,
            'from_status' => $fromStatus instanceof MaintenanceWorkOrderStatus ? $fromStatus->value : $fromStatus,
            'to_status' => $toStatus instanceof MaintenanceWorkOrderStatus ? $toStatus->value : $toStatus,
            'from_assigned_employee_id' => $fromEmployeeId,
            'to_assigned_employee_id' => $toEmployeeId,
            'data' => $data,
            'occurred_at' => now(),
        ]);
    }

    private function advance(Carbon $date, string $unit, int $interval): Carbon
    {
        return match ($unit) {
            'days' => $date->copy()->addDays($interval),
            'weeks' => $date->copy()->addWeeks($interval),
            'months' => $date->copy()->addMonthsNoOverflow($interval),
            'years' => $date->copy()->addYearsNoOverflow($interval),
            default => throw ValidationException::withMessages(['recurrence_unit' => __('fieldops::resource.work_orders.validation.invalid_recurrence')]),
        };
    }
}
