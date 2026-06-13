<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorInvoice extends Model
{
    protected $table = 'intelligence_mirror_invoices';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'project_id',
        'relation_id',
        'total_price_vat_excl',
        'total_price',
        'total_paid',
        'date',
        'date_expiration',
        'fl_paid',
    ];

    protected $casts = [
        'total_price_vat_excl' => 'float',
        'total_price'          => 'decimal:4',
        'total_paid'           => 'decimal:4',
        'date'                 => 'date',
        'date_expiration'      => 'date',
        'fl_paid'              => 'boolean',
    ];
}
