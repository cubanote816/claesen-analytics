<?php

namespace Modules\Safety\Models;

use Illuminate\Database\Eloquent\Model;

class SafetyAdoptionDailyRollup extends Model
{
    protected $table = 'safety_adoption_daily_rollups';

    protected $fillable = [
        'date',
        'metric_name',
        'project_id',
        'value',
    ];

    protected $casts = [
        'date' => 'date',
        'value' => 'decimal:2',
    ];
}
