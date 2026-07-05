<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorRelationDelivery extends Model
{
    protected $table = 'intelligence_mirror_relation_deliveries';

    protected $fillable = [
        'relation_id',
        'seq_nr',
        'name',
        'street',
        'city',
        'zipcode',
        'fl_active',
    ];

    protected $casts = [
        'fl_active' => 'boolean',
    ];
}
