<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorCost extends Model
{
    protected $table = 'intelligence_mirror_costs';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'project_id',
        'cost_price',
        'quantity',
        'date',
        'cost_descr',
    ];

    protected $casts = [
        'cost_price' => 'float',
        'quantity' => 'float',
        'date' => 'date',
    ];
}
