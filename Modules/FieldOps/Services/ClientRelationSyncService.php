<?php

namespace Modules\FieldOps\Services;

use Modules\FieldOps\Models\FoClient;
use Modules\Performance\Models\Mirror\MirrorRelation;

class ClientRelationSyncService
{
    /**
     * Upsert FoClient records from CAFCA relations already mirrored locally
     * (Modules\Intelligence\Services\SyncMirrorDataService::syncRelations()).
     *
     * Only relations flagged tp_customer=1 are imported — the ERP relation
     * table also holds suppliers/carriers/subcontractors under the same table.
     * Idempotent via fo_clients.relation_id, so re-running keeps existing
     * FoClients (and any Complex already linked to them) in place.
     */
    public function sync(): int
    {
        $count = 0;

        MirrorRelation::query()
            ->where('tp_customer', true)
            ->orderBy('id')
            ->chunk(500, function ($relations) use (&$count) {
                foreach ($relations as $relation) {
                    $attributes = [
                        'name'     => $relation->name ?: $relation->id,
                        'city'     => $relation->city ?: null,
                        'street'   => $relation->street ?: null,
                        'phone'    => $relation->phone ?: null,
                        'email'    => $relation->email ?: null,
                        'language' => $relation->language ?: 'nl',
                    ];

                    // withTrashed() so a client soft-deleted in Filament doesn't collide
                    // with the unique relation_id index on the next sync run.
                    $client = FoClient::withTrashed()->firstOrNew(['relation_id' => $relation->id]);
                    $client->fill($attributes);
                    if ($client->trashed()) {
                        $client->restore();
                    }
                    $client->save();

                    $count++;
                }
            });

        return $count;
    }
}
