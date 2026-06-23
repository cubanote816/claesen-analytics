<?php

namespace Modules\Safety\Models;

use Illuminate\Database\Eloquent\Model;

class SafetyEnabledUserSnapshot extends Model
{
    protected $table = 'safety_enabled_user_snapshots';

    protected $fillable = [
        'date',
        'total_enabled_users',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
