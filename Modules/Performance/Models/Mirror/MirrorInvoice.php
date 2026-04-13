<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorInvoice extends Model
{
    protected $table = 'analytics_mirror_invoices';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'project_id',
        'total_price_vat_excl',
        'date',
    ];

    protected $casts = [
        'total_price_vat_excl' => 'float',
        'date' => 'date',
    ];
}
