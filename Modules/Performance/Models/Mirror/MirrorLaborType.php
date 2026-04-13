<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorLaborType extends Model
{
    protected $table = 'intelligence_mirror_labor_types';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'cost_price',
    ];

    protected $casts = [
        'cost_price' => 'float',
    ];
}
