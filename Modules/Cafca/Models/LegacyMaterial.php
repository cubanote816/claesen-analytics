<?php

namespace Modules\Cafca\Models;

use Modules\Core\Traits\ReadOnlyTrait;

class LegacyMaterial extends CafcaModel
{
    use ReadOnlyTrait;

    protected $table = 'material';
    protected $primaryKey = 'id';

    protected $casts = [
        'costprice' => 'float',
        'date' => 'datetime',
        'fl_current' => 'boolean',
    ];
}
