<?php

namespace Modules\Intelligence\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorCost extends Model
{
    protected $table = 'intelligence_mirror_costs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'project_id',
        'art_id',
        'descr',
        'type',
        'cost_price',
        'quantity',
        'extra_type',
        'date',
    ];

    protected $casts = [
        'cost_price' => 'float',
        'quantity' => 'float',
        'date' => 'date',
    ];
}
