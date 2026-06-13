<?php

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Model;

class BiConfig extends Model
{
    protected $table = 'intelligence_bi_config';

    protected $fillable = [
        'config_key',
        'config_value',
        'label',
        'description',
        'updated_by',
    ];

    protected $casts = [
        'config_value' => 'array',
    ];
}
