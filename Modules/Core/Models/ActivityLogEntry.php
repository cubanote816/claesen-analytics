<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * F2/CLA-465 of the multi-organization program — docs/ai/adr-multi-organization.md,
 * decision D3. `config('activitylog.activity_model')` points here instead of
 * the vendor's own `Spatie\Activitylog\Models\Activity`, the documented
 * extension point for exactly this — every activity created anywhere in the
 * app (LogsActivity models, the bare `activity()` helper, the panel-switch
 * audit trail) goes through this class without any of those call sites
 * needing to know about it.
 *
 * Two responsibilities, both additive to the vendor model:
 *
 * 1. Auto-fill `organization_id` (D3) from the causer's own organization when
 *    it isn't set explicitly — every LogsActivity call and every plain
 *    `activity()->causedBy($user)->log(...)` gets this for free.
 * 2. Append-only enforcement: an activity log entry can be created, never
 *    updated or deleted through Eloquent. The package's own retention
 *    cleanup (`activitylog:clean`, CleanActivityLogAction) issues a bulk
 *    query-builder DELETE — that never fires Eloquent's `deleting` event, so
 *    it is unaffected by the guard below. Only an accidental `$activity->
 *    delete()`/`update()` from application code is what this blocks.
 */
class ActivityLogEntry extends BaseActivity
{
    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (self $activity): void {
            if ($activity->organization_id === null) {
                $activity->organization_id = static::resolveOrganizationId($activity);
            }
        });

        static::updating(function (): void {
            throw new \LogicException('Activity log entries are append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new \LogicException(
                'Activity log entries are append-only and cannot be deleted through Eloquent. '
                .'Retention cleanup runs via the activitylog:clean command.'
            );
        });
    }

    private static function resolveOrganizationId(self $activity): ?int
    {
        $causer = $activity->causer;

        return $causer instanceof User ? $causer->organization_id : null;
    }
}
