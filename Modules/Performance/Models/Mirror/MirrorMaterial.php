<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorMaterial extends Model
{
    protected $table = 'intelligence_mirror_materials';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'unit',
        'cost_price',
        'ai_category',
        'ai_labels',
        'last_audited_at',
    ];

    protected $casts = [
        'cost_price' => 'float',
        'ai_labels' => 'array',
        'last_audited_at' => 'datetime',
    ];
}
