<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorLabor extends Model
{
    protected $table = 'intelligence_mirror_labor';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'project_id',
        'employee_id',
        'labor_id',
        'hours',
        'date',
        'labor_descr',
        'h_from_1',
        'h_to_1',
        'h_from_2',
        'h_to_2',
        'distance',
        'fl_approved',
        'total_costprice',
        'total_salesprice',
        'pauze',
        'fl_pauze',
        'productivity',
        'transport_costprice',
        'transport_salesprice',
    ];

    protected $casts = [
        'hours'            => 'float',
        'date'             => 'date',
        'distance'         => 'float',
        'fl_approved'      => 'boolean',
        'total_costprice'  => 'float',
        'total_salesprice' => 'float',
        'pauze'            => 'float',
        'fl_pauze'              => 'boolean',
        'productivity'          => 'float',
        'transport_costprice'   => 'float',
        'transport_salesprice'  => 'float',
    ];
}
