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
    ];

    protected $casts = [
        'hours' => 'float',
        'date' => 'date',
    ];
}
