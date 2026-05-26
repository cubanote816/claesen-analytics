<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorEstimateItem extends Model
{
    protected $table = 'intelligence_mirror_estimate_items';

    protected $fillable = [
        'estimate_id',
        'project_id',
        'sequence',
        'line_type',
        'ref',
        'description',
        'quantity',
        'unit',
        'unit_price_material',
        'unit_price_labor',
        'hours_per_unit',
        'total_hours',
    ];

    protected $casts = [
        'sequence'            => 'integer',
        'quantity'            => 'float',
        'unit_price_material' => 'float',
        'unit_price_labor'    => 'float',
        'hours_per_unit'      => 'float',
        'total_hours'         => 'float',
    ];

    public function scopeItemLines($query)
    {
        return $query->where('line_type', 'partida');
    }

    public function scopeForProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId)->orderBy('sequence');
    }
}
