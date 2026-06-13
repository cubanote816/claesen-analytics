<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorProjectResult extends Model
{
    protected $table = 'intelligence_mirror_project_results';
    protected $primaryKey = 'project_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'project_id',
        'project_name',
        'relation_id',
        'relation_name',
        'dossier',
        'costprice_material',
        'costprice_labor',
        'costprice_equipment',
        'costprice_subcontract',
        'costprice_extra',
        'costprice_transport',
        'costprice_total',
        'invoiced',
        'profit',
        'profit_percent',
        'profit_percent_estimates',
        'total_estimates',
        'total_regie',
        'hours_regie',
        'oh',
        'project_uren',
        'voorz_uren',
        'uren_projectleader',
        'current_costs_booked',
        'synced_at',
    ];

    protected $casts = [
        'costprice_material'        => 'decimal:4',
        'costprice_labor'           => 'decimal:4',
        'costprice_equipment'       => 'decimal:4',
        'costprice_subcontract'     => 'decimal:4',
        'costprice_extra'           => 'decimal:4',
        'costprice_transport'       => 'decimal:4',
        'costprice_total'           => 'decimal:4',
        'invoiced'                  => 'decimal:4',
        'profit'                    => 'decimal:4',
        'profit_percent'            => 'decimal:4',  // decimal(10,4) — can exceed 9999% on low-cost projects
        'profit_percent_estimates'  => 'decimal:4',
        'total_estimates'           => 'decimal:4',
        'total_regie'               => 'decimal:4',
        'hours_regie'               => 'decimal:2',
        'oh'                        => 'decimal:2',
        'project_uren'              => 'decimal:2',
        'voorz_uren'                => 'decimal:2',
        'uren_projectleader'        => 'decimal:2',
        'current_costs_booked'      => 'boolean',
        'synced_at'                 => 'datetime',
    ];
}
