<?php

declare(strict_types=1);

namespace Modules\FieldOps\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Models\FoMaintenancePlan;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;

class MaintenanceWorkOrderService
{
    public function __construct(private readonly MaintenanceEquipmentContextService $context) {}

    public function create(array $data, ?int $userId): FoMaintenanceWorkOrder
    {
        return DB::transaction(function () use ($data, $userId): FoMaintenanceWorkOrder {
            $context = $this->context->resolve($data['maintainable_type'], (int) $data['maintainable_id']);
            $recurrenceUnit = $data['recurrence_unit'] ?? null;
            $recurrenceInterval = (int) ($data['recurrence_interval'] ?? 1);
            unset($data['recurrence_unit'], $data['recurrence_interval']);

            $data = array_merge($data, [
                'created_by_user_id' => $userId,
                'client_id' => $context['client_id'],
                'luminaire_position_id' => $context['luminaire_position_id'],
                'status' => ! empty($data['assigned_employee_id'])
                    ? MaintenanceWorkOrderStatus::ASSIGNED
                    : MaintenanceWorkOrderStatus::PLANNED,
                'source' => $data['source'] ?? 'backoffice',
            ]);

            $order = FoMaintenanceWorkOrder::query()->create($data);

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

            return $order->fresh();
        });
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

        foreach ($plans as $plan) {
            DB::transaction(function () use ($plan): void {
                $locked = FoMaintenancePlan::query()->lockForUpdate()->findOrFail($plan->id);
                if (! $locked->is_active || $locked->next_due_at->isFuture()) {
                    return;
                }

                FoMaintenanceWorkOrder::query()->create([
                    'created_by_user_id' => $locked->created_by_user_id,
                    'maintenance_plan_id' => $locked->id,
                    'fo_maintenance_type_id' => $locked->fo_maintenance_type_id,
                    'maintainable_type' => $locked->maintainable_type,
                    'maintainable_id' => $locked->maintainable_id,
                    'luminaire_position_id' => $locked->luminaire_position_id,
                    'client_id' => $locked->client_id,
                    'assigned_employee_id' => $locked->assigned_employee_id,
                    'status' => $locked->assigned_employee_id ? MaintenanceWorkOrderStatus::ASSIGNED : MaintenanceWorkOrderStatus::PLANNED,
                    'priority' => 'medium',
                    'source' => 'maintenance_plan',
                    'scheduled_for' => $locked->next_due_at,
                    'instructions' => $locked->instructions,
                ]);

                $locked->update([
                    'next_due_at' => $this->advance($locked->next_due_at, $locked->recurrence_unit, $locked->recurrence_interval),
                ]);
            });
        }

        return $plans->count();
    }

    public function start(FoMaintenanceWorkOrder $order, int $userId): FoMaintenanceWorkOrder
    {
        if (! in_array($order->status, [MaintenanceWorkOrderStatus::PLANNED, MaintenanceWorkOrderStatus::ASSIGNED], true)) {
            throw ValidationException::withMessages(['status' => __('fieldops::resource.work_orders.validation.cannot_start')]);
        }

        $order->update([
            'status' => MaintenanceWorkOrderStatus::IN_PROGRESS,
            'started_at' => now(),
            'started_by_user_id' => $userId,
        ]);

        return $order->fresh();
    }

    public function submit(FoMaintenanceWorkOrder $order, array $data, int $userId): FoMaintenanceWorkOrder
    {
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

        return $order->fresh();
    }

    public function close(FoMaintenanceWorkOrder $order, int $userId, ?string $overrideReason = null): FoMaintenanceWorkOrder
    {
        return DB::transaction(function () use ($order, $userId, $overrideReason): FoMaintenanceWorkOrder {
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

            return $order->fresh(['maintenanceRecord']);
        });
    }

    public function cancel(FoMaintenanceWorkOrder $order, int $userId, string $reason): FoMaintenanceWorkOrder
    {
        if (in_array($order->status, [MaintenanceWorkOrderStatus::COMPLETED, MaintenanceWorkOrderStatus::CANCELLED], true)) {
            throw ValidationException::withMessages(['status' => __('fieldops::resource.work_orders.validation.cannot_cancel')]);
        }

        $order->update([
            'status' => MaintenanceWorkOrderStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $userId,
            'cancellation_reason' => $reason,
        ]);

        return $order->fresh();
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
