<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorEmployee extends Model
{
    protected $table = 'intelligence_mirror_employees';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'zipcode',
        'specialty',
        'hourly_cost',
        'fl_active',
    ];

    protected $casts = [
        'fl_active' => 'boolean',
    ];
}
