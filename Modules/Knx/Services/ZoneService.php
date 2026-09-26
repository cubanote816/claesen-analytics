<?php

namespace Modules\Knx\Services;

use Illuminate\Validation\ValidationException;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxZone;
use Modules\Knx\Models\KnxZoneCheck;

/**
 * Updating one readiness check.
 *
 * The only rule that needs enforcing here is the one §1.4 makes explicit:
 * **`failed` and `na` must explain themselves.** A blocked zone whose reason is
 * empty is exactly the useless signal this model exists to avoid — the office
 * needs to know *what* is missing and *who* owns it, and `na` needs to say why the
 * check does not apply instead of silently disappearing from the derivation.
 *
 * Everything else (`status`, `blockingReason`, `blockedBy`) is recomputed by the
 * model on read, so this service never writes them.
 */
class ZoneService
{
    public function updateCheck(
        KnxZone $zone,
        string $key,
        string $status,
        ?string $note,
        ?KnxEmployee $by,
    ): KnxZone {
        if (! in_array($key, KnxZoneCheck::keys(), true)) {
            throw ValidationException::withMessages([
                'key' => __('knx::zones.unknown_check'),
            ]);
        }

        $check = $zone->checks()->where('key', $key)->first();

        if ($check === null) {
            // Every zone is created with its eight checks; a missing one means the
            // row was removed out of band, and pretending it worked would lie.
            throw ValidationException::withMessages([
                'key' => __('knx::zones.unknown_check'),
            ]);
        }

        if (in_array($status, [KnxZoneCheck::STATUS_FAILED, KnxZoneCheck::STATUS_NA], true) && blank($note)) {
            throw ValidationException::withMessages([
                'note' => $status === KnxZoneCheck::STATUS_FAILED
                    ? __('knx::zones.note_required_failed')
                    : __('knx::zones.note_required_na'),
            ]);
        }

        $check->fill([
            'status' => $status,
            // A passed/pending check carries no explanation.
            'note' => in_array($status, [KnxZoneCheck::STATUS_FAILED, KnxZoneCheck::STATUS_NA], true) ? $note : null,
            'updated_by_employee_id' => $by?->getKey(),
            'updated_at' => now(),
        ])->save();

        return $zone->refresh()->load(['project', 'checks.updatedBy']);
    }
}
