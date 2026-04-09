<?php

namespace Modules\Analytics\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorProject extends Model
{
    protected $table = 'analytics_mirror_projects';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'relation_id',
        'category',
        'zipcode',
        'city',
        'fl_active',
        'last_modified_at',
    ];

    protected $casts = [
        'fl_active' => 'boolean',
        'last_modified_at' => 'datetime',
    ];
}
