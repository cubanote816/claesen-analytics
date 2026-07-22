<?php

declare(strict_types=1);

namespace Modules\FieldOps\Services;

use Modules\Core\Models\User;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Notifications\WorkOrderOperationalNotification;

class WorkOrderNotificationService
{
    public function assignmentChanged(
        FoMaintenanceWorkOrder $order,
        ?string $previousEmployeeId,
        ?string $newEmployeeId,
    ): void {
        if ($previousEmployeeId && $previousEmployeeId !== $newEmployeeId) {
            $this->notifyEmployee($previousEmployeeId, $order, 'reassigned_away', 'worker');
        }

        if ($newEmployeeId && $newEmployeeId !== $previousEmployeeId) {
            $this->notifyEmployee(
                $newEmployeeId,
                $order,
                $previousEmployeeId ? 'reassigned' : 'assigned',
                'worker',
            );
        }
    }

    public function submitted(FoMaintenanceWorkOrder $order): void
    {
        $this->notifyAssigner($order, 'submitted');
    }

    public function returned(FoMaintenanceWorkOrder $order, string $reason): void
    {
        if ($order->assigned_employee_id) {
            $this->notifyEmployee($order->assigned_employee_id, $order, 'returned', 'worker', $reason);
        }
    }

    public function completed(FoMaintenanceWorkOrder $order): void
    {
        $this->notifyAssigner($order, 'completed');
    }

    public function cancelled(FoMaintenanceWorkOrder $order, string $reason): void
    {
        $this->notifyAssigner($order, 'cancelled', $reason);
    }

    public function activeUserForEmployee(?string $employeeId): ?User
    {
        if (! $employeeId) {
            return null;
        }

        return User::query()
            ->where('employee_id', $employeeId)
            ->where('is_active', true)
            ->first();
    }

    private function notifyEmployee(
        string $employeeId,
        FoMaintenanceWorkOrder $order,
        string $event,
        string $audience,
        ?string $reason = null,
    ): void {
        $user = $this->activeUserForEmployee($employeeId);

        if ($user) {
            $this->notify($user, $order, $event, $audience, $reason);
        }
    }

    private function notifyAssigner(FoMaintenanceWorkOrder $order, string $event, ?string $reason = null): void
    {
        $user = $order->assigned_by_user_id
            ? User::query()->whereKey($order->assigned_by_user_id)->where('is_active', true)->first()
            : null;

        if ($user) {
            $this->notify($user, $order, $event, 'backoffice', $reason);
        }
    }

    private function notify(
        User $user,
        FoMaintenanceWorkOrder $order,
        string $event,
        string $audience,
        ?string $reason,
    ): void {
        $notification = (new WorkOrderOperationalNotification($order, $event, $audience, $reason))
            ->locale(in_array($user->language, ['nl', 'en'], true) ? $user->language : 'en');

        $user->notify($notification);
    }
}
