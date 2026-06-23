<?php

namespace Modules\Safety\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\User;
use Modules\Safety\Models\SafetyAdoptionDailyRollup;
use Modules\Safety\Models\SafetyAdoptionEvent;
use Modules\Safety\Models\SafetyEnabledUserSnapshot;

class SafetyAdoptionMetricsService
{
    /**
     * Define who is an "enabled user".
     * According to the plan, this definition should be aligned with business.
     * Currently defaulting to any User that has a web access role/permission, 
     * but we will use all active users as a safe placeholder until formal definition.
     */
    public function getEnabledUsersCount(): int
    {
        // Placeholder definition: all users that are not explicitly marked inactive
        // We will just count all users for now.
        return User::count();
    }

    public function aggregateForDate(Carbon $date): void
    {
        $dateString = $date->toDateString();

        // 1. Snapshot the denominator
        $enabledCount = $this->getEnabledUsersCount();
        SafetyEnabledUserSnapshot::updateOrCreate(
            ['date' => $dateString],
            ['total_enabled_users' => $enabledCount]
        );

        // 2. Aggregate counts for the specific date
        // Total completed inspections
        $completedCount = SafetyAdoptionEvent::where('event_type', 'inspection_completed')
            ->whereDate('created_at', $date)
            ->count();
            
        $this->storeRollup($dateString, 'inspections_completed', $completedCount);

        // Total friction events
        $frictionCount = SafetyAdoptionEvent::whereIn('event_type', ['photo_upload_failed', 'inspection_payload_conflict'])
            ->whereDate('created_at', $date)
            ->count();
            
        $this->storeRollup($dateString, 'friction_events_count', $frictionCount);

        // 3. WAU (7 days) and MAU (30 days) Active Users
        // Active 7 days
        $active7d = SafetyAdoptionEvent::where('event_type', 'inspection_completed')
            ->whereDate('created_at', '>', $date->copy()->subDays(7))
            ->whereDate('created_at', '<=', $date)
            ->distinct('user_id')
            ->count('user_id');

        $this->storeRollup($dateString, 'active_users_7d', $active7d);

        // Active 30 days
        $active30d = SafetyAdoptionEvent::where('event_type', 'inspection_completed')
            ->whereDate('created_at', '>', $date->copy()->subDays(30))
            ->whereDate('created_at', '<=', $date)
            ->distinct('user_id')
            ->count('user_id');

        $this->storeRollup($dateString, 'active_users_30d', $active30d);

        // 4. Trend by project (Inspections completed per project today)
        $projectCounts = SafetyAdoptionEvent::select('project_id', DB::raw('count(*) as count'))
            ->where('event_type', 'inspection_completed')
            ->whereDate('created_at', $date)
            ->whereNotNull('project_id')
            ->groupBy('project_id')
            ->get();

        foreach ($projectCounts as $row) {
            $this->storeRollup($dateString, 'inspections_completed_project', $row->count, $row->project_id);
        }
    }

    public function purgeOldEvents(int $daysToKeep = 90): int
    {
        $cutoff = Carbon::now()->subDays($daysToKeep);
        return SafetyAdoptionEvent::where('created_at', '<', $cutoff)->delete();
    }

    private function storeRollup(string $date, string $metric, $value, ?string $projectId = null): void
    {
        SafetyAdoptionDailyRollup::updateOrCreate(
            [
                'date' => $date,
                'metric_name' => $metric,
                'project_id' => $projectId,
            ],
            ['value' => $value]
        );
    }
}
