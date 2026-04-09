<?php

namespace Modules\Analytics\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorLabor extends Model
{
    protected $table = 'analytics_mirror_labor';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'project_id',
        'employee_id',
        'labor_id',
        'hours',
        'date',
    ];

    protected $casts = [
        'hours' => 'float',
        'date' => 'date',
    ];
}
