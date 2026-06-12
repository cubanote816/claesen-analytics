<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorEstimateCalc extends Model
{
    protected $table = 'intelligence_mirror_estimate_calc';
    protected $primaryKey = 'estimate_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'estimate_id',
        'factor_material',
        'factor_labor',
        'factor_equipment',
        'factor_subcontract',
        'factor_qty_labor',
        'factor_qty_material',
        'factor_unitprice',
        'labor_c_price',
        'additional_hours',
        'qty_employees',
        'extra_costs_json',
    ];

    protected $casts = [
        'factor_material'    => 'decimal:4',
        'factor_labor'       => 'decimal:4',
        'factor_equipment'   => 'decimal:4',
        'factor_subcontract' => 'decimal:4',
        'factor_qty_labor'   => 'decimal:4',
        'factor_qty_material'=> 'decimal:4',
        'factor_unitprice'   => 'decimal:4',
        'labor_c_price'      => 'decimal:2',
        'additional_hours'   => 'decimal:2',
        'extra_costs_json'   => 'array',
    ];
}
