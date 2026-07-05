<?php

namespace Modules\FieldOps\Services;

use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoClient;
use Modules\Performance\Models\Mirror\MirrorRelationDelivery;

class ComplexRelationDeliverySyncService
{
    public function __construct(private readonly GeocodingService $geocoding)
    {
    }

    /**
     * Upsert Complex records from CAFCA relation_delivery rows already mirrored
     * locally (Modules\Intelligence\Services\SyncMirrorDataService::syncRelationDeliveries()).
     *
     * Only deliveries whose relation_id resolves to an already-synced FoClient
     * are imported (see Modules\FieldOps\Services\ClientRelationSyncService) —
     * relation_id=0 and other non-customer stragglers are skipped.
     *
     * Idempotent via (relation_id, delivery_seq_nr). relation_delivery carries no
     * coordinates, so lat/lng are geocoded from the address on first import only —
     * a complex that already has coordinates (geocoded before, or pinned manually
     * via the map picker) is never re-geocoded/overwritten on subsequent runs.
     */
    public function sync(): int
    {
        $count = 0;

        MirrorRelationDelivery::query()
            ->where('fl_active', true)
            ->where('relation_id', '!=', 0)
            ->orderBy('relation_id')
            ->orderBy('seq_nr')
            ->chunk(500, function ($deliveries) use (&$count) {
                foreach ($deliveries as $delivery) {
                    $clientId = FoClient::where('relation_id', $delivery->relation_id)->value('id');

                    if ($clientId === null) {
                        continue;
                    }

                    $complex = Complex::withTrashed()->firstOrNew([
                        'relation_id'     => $delivery->relation_id,
                        'delivery_seq_nr' => $delivery->seq_nr,
                    ]);

                    $complex->fill([
                        'client_id' => $clientId,
                        'name'      => $delivery->name ?: $delivery->street,
                        'street'    => $delivery->street ?: null,
                        'city'      => $delivery->city ?: null,
                        'zipcode'   => $delivery->zipcode ?: null,
                    ]);

                    if ($complex->trashed()) {
                        $complex->restore();
                    }

                    if ($complex->lat === null && $complex->lng === null) {
                        $coordinates = $this->geocoding->geocode(
                            $delivery->street,
                            $delivery->city,
                            $delivery->zipcode
                        );

                        if ($coordinates !== null) {
                            $complex->fill($coordinates);
                        }
                    }

                    $complex->save();

                    $count++;
                }
            });

        return $count;
    }
}
