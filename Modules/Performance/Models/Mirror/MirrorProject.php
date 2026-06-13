<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorProject extends Model
{
    protected $table = 'intelligence_mirror_projects';
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
        'contract_price',
        'type',
        'state',
        'last_modified_at',
    ];

    protected $casts = [
        'fl_active'      => 'boolean',
        'contract_price' => 'decimal:2',
        'last_modified_at' => 'datetime',
    ];
}
