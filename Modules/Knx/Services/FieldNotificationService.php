<?php

namespace Modules\Knx\Services;

use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxNotification;

/**
 * Confirming what the field reported.
 *
 * One rule lives here and nowhere else: **confirming a registration also confirms
 * the apparatus it created.** That is what clears `isNew` in the dossier — the
 * two rows are the same fact seen from two sides (the event and the registry), so
 * leaving the device unconfirmed after the office said "yes, I have seen it" would
 * keep showing an alert for something already triaged.
 *
 * Both operations are idempotent: confirming twice is not an error, it is what a
 * front that polls every 5 s will inevitably do.
 */
class FieldNotificationService
{
    public function ack(KnxNotification $notification): void
    {
        if ($notification->isAcked()) {
            return;
        }

        $now = now();

        $notification->forceFill(['acknowledged_at' => $now])->save();

        $notification->device?->forceFill(['acknowledged_at' => $now])->save();
    }

    /**
     * Confirms every pending item, in two statements instead of two per row.
     *
     * @return int how many were pending
     */
    public function ackAll(): int
    {
        $pending = KnxNotification::query()->pending();

        $deviceIds = (clone $pending)->pluck('device_id')->filter()->all();
        $acknowledged = (clone $pending)->update(['acknowledged_at' => now()]);

        if ($deviceIds !== []) {
            KnxDevice::query()
                ->whereIn('id', $deviceIds)
                ->whereNull('acknowledged_at')
                ->update(['acknowledged_at' => now()]);
        }

        return $acknowledged;
    }
}
