<?php

namespace Modules\Cafca\Models;

use Modules\Core\Traits\ReadOnlyTrait;

class EstimateItem extends CafcaModel
{
    use ReadOnlyTrait;

    protected $table = 'estimate_item';
    
    protected $primaryKey = 'estimate_id'; // Not strictly true (it's composite), but works for read-only relations
    public $incrementing = false;

    protected $casts = [
        'total_hours' => 'float',
        'quantity' => 'float',
    ];
}
