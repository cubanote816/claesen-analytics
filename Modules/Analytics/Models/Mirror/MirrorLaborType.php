<?php

namespace Modules\Analytics\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorLaborType extends Model
{
    protected $table = 'analytics_mirror_labor_types';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'ref',
        'name',
    ];
}

// Separate file logic: I will save them one by one to ensure file creation tool works correctly.
