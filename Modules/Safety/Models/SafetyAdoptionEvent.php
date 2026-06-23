<?php

namespace Modules\Safety\Models;

use Illuminate\Database\Eloquent\Model;

class SafetyAdoptionEvent extends Model
{
    protected $table = 'safety_adoption_events';

    protected $fillable = [
        'user_id',
        'event_type',
        'project_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
