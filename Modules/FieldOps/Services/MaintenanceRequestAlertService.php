<?php

declare(strict_types=1);

namespace Modules\FieldOps\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\User;
use Modules\FieldOps\Enums\MaintenanceRequestAlertType;
use Modules\FieldOps\Enums\MaintenanceRequestStatus;
use Modules\FieldOps\Models\FoMaintenanceRequest;
use Modules\FieldOps\Models\FoMaintenanceRequestAlert;
use Modules\FieldOps\Notifications\MaintenanceRequestAlertNotification;

/**
 * Checks maintenance requests against the Fase 5 operational SLA thresholds
 * (first response, client confirmation) and keeps fo_maintenance_request_alerts
 * in sync: fires a new alert on breach, auto-resolves it once the condition
 * clears, and re-fires on a later reopen cycle since the (request, type)
 * row is reused rather than left permanently closed.
 */
class MaintenanceRequestAlertService
{
    /** @return array{created: int, resolved: int} */
    public function check(bool $dryRun = false): array
    {
        $firstResponseHours = (int) config('fieldops.request_alerts.first_response_hours', 24);
        $confirmationWaitDays = (int) config('fieldops.request_alerts.confirmation_wait_days', 7);

        $created = $this->fireBreaches(
            MaintenanceRequestAlertType::NO_FIRST_RESPONSE,
            FoMaintenanceRequest::query()
                ->whereIn('status', [MaintenanceRequestStatus::RECEIVED, MaintenanceRequestStatus::IN_REVIEW])
                ->whereNull('acknowledged_at')
                ->where('created_at', '<=', now()->subHours($firstResponseHours)),
            $dryRun,
        );

        $created += $this->fireBreaches(
            MaintenanceRequestAlertType::AWAITING_CONFIRMATION,
            FoMaintenanceRequest::query()
                ->where('status', MaintenanceRequestStatus::RESOLVED)
                ->whereNull('confirmed_at')
                ->where('resolved_at', '<=', now()->subDays($confirmationWaitDays)),
            $dryRun,
        );

        $resolved = $this->resolveCleared(
            MaintenanceRequestAlertType::NO_FIRST_RESPONSE,
            fn (FoMaintenanceRequest $request): bool => $request->acknowledged_at === null
                && in_array($request->status, [MaintenanceRequestStatus::RECEIVED, MaintenanceRequestStatus::IN_REVIEW], true),
            $dryRun,
        );

        $resolved += $this->resolveCleared(
            MaintenanceRequestAlertType::AWAITING_CONFIRMATION,
            fn (FoMaintenanceRequest $request): bool => $request->confirmed_at === null
                && $request->status === MaintenanceRequestStatus::RESOLVED,
            $dryRun,
        );

        return ['created' => $created, 'resolved' => $resolved];
    }

    private function fireBreaches(MaintenanceRequestAlertType $type, Builder $query, bool $dryRun): int
    {
        $count = 0;

        foreach ($query->get() as $request) {
            $alreadyOpen = FoMaintenanceRequestAlert::query()
                ->where('maintenance_request_id', $request->id)
                ->where('alert_type', $type->value)
                ->whereNull('resolved_at')
                ->exists();

            if ($alreadyOpen) {
                continue;
            }

            $count++;

            if ($dryRun) {
                continue;
            }

            $alert = FoMaintenanceRequestAlert::updateOrCreate(
                ['maintenance_request_id' => $request->id, 'alert_type' => $type->value],
                ['triggered_at' => now(), 'resolved_at' => null],
            );

            $this->notifyBackoffice($alert);
        }

        return $count;
    }

    /** @param callable(FoMaintenanceRequest): bool $stillBreaching */
    private function resolveCleared(MaintenanceRequestAlertType $type, callable $stillBreaching, bool $dryRun): int
    {
        $count = 0;

        $open = FoMaintenanceRequestAlert::query()
            ->where('alert_type', $type->value)
            ->whereNull('resolved_at')
            ->with('request')
            ->get();

        foreach ($open as $alert) {
            $request = $alert->request;

            if ($request && $stillBreaching($request)) {
                continue;
            }

            $count++;

            if ($dryRun) {
                continue;
            }

            $alert->update(['resolved_at' => now()]);
        }

        return $count;
    }

    private function notifyBackoffice(FoMaintenanceRequestAlert $alert): void
    {
        User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'super_admin']))
            ->each(fn (User $user) => $user->notify(new MaintenanceRequestAlertNotification($alert)));
    }
}
