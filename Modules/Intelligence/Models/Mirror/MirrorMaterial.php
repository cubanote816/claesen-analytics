<?php

namespace Modules\Intelligence\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorMaterial extends Model
{
    protected $table = 'intelligence_mirror_materials';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'ref',
        'description',
        'category_ai',
        'tags',
        'usage_summary',
        'modern_id',
        'cost_price',
        'last_price_date',
        'fl_active',
        'last_learned_at',
    ];

    protected $casts = [
        'cost_price' => 'float',
        'last_price_date' => 'date',
        'fl_active' => 'boolean',
        'tags' => 'array',
        'last_learned_at' => 'datetime',
    ];
}
