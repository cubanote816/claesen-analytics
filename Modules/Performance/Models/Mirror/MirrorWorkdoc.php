<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorWorkdoc extends Model
{
    protected $table = 'intelligence_mirror_workdocs';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'project_id',
        'relation_id',
        'name',
        'date',
        'status',
        'fl_invoice',
        'fl_finished',
        'fl_paid',
        'total_price',
        'total_paid',
        'synced_at',
    ];

    protected $casts = [
        'date'        => 'date',
        'fl_invoice'  => 'boolean',
        'fl_finished' => 'boolean',
        'fl_paid'     => 'boolean',
        'total_price' => 'decimal:4',
        'total_paid'  => 'decimal:4',
        'synced_at'   => 'datetime',
    ];
}
