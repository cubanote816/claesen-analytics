<?php

declare(strict_types=1);

namespace Modules\Safety\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Models\User;
use Modules\Safety\Models\Inspection;

class InspectionReminderService
{
    /**
     * Returns project_manager users who have not completed an inspection
     * within the required period, along with how many days since their last one.
     *
     * Rules:
     * - Only users with role 'project_manager' are evaluated.
     * - A user who created their account fewer than $graceDays ago is skipped
     *   (new accounts get time to onboard before being reminded).
     * - Soft-deleted inspections count: archiving is an admin action and does
     *   not undo the fact that an inspection was performed.
     * - Boundary: >= $days (inclusive). A user whose last inspection was
     *   exactly $days ago receives the reminder.
     *
     * @param  int|null  $days      Override safety.compliance_days threshold.
     * @param  int|null  $graceDays Override safety.reminder_grace_days grace period.
     * @return Collection<int, array{user: User, days_since_last: int|null}>
     */
    public function getInactiveProjectManagers(?int $days = null, ?int $graceDays = null): Collection
    {
        $days      = $days      ?? (int) config('safety.compliance_days');
        $graceDays = $graceDays ?? (int) config('safety.reminder_grace_days');

        $cutoff    = Carbon::now()->subDays($days);
        $graceDate = Carbon::now()->subDays($graceDays);

        $managers = User::role('project_manager')->get();

        return $managers
            ->filter(function (User $user) use ($graceDate): bool {
                // Skip users still within the onboarding grace period.
                // Use the user's created_at for a consistent, testable reference point.
                return Carbon::parse($user->created_at)->lessThanOrEqualTo($graceDate);
            })
            ->map(function (User $user) use ($cutoff): ?array {
                // withTrashed() is intentional: archived inspections count as evidence
                // that an inspection was performed, regardless of later admin archiving.
                $lastInspectedAt = Inspection::withTrashed()
                    ->where('user_id', $user->id)
                    ->max('completed_at');

                if ($lastInspectedAt !== null) {
                    $lastCarbon = Carbon::parse($lastInspectedAt);

                    // Compliant: last inspection is more recent than the cutoff.
                    if ($lastCarbon->greaterThan($cutoff)) {
                        return null;
                    }

                    return [
                        'user'             => $user,
                        'days_since_last'  => (int) $lastCarbon->diffInDays(Carbon::now()),
                    ];
                }

                // No inspection history at all — always needs a reminder.
                return [
                    'user'            => $user,
                    'days_since_last' => null,
                ];
            })
            ->filter()
            ->values();
    }
}
