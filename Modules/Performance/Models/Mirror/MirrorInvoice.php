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

    public function scopeRegularInvoices($query)
    {
        return $query->where('id', 'NOT LIKE', 'CN%');
    }

    public function scopeCreditNotes($query)
    {
        return $query->where('id', 'LIKE', 'CN%');
    }

    public function getIsCreditNoteAttribute(): bool
    {
        return str_starts_with((string) $this->id, 'CN');
    }

    public function getSignedTotalPriceVatExclAttribute(): float
    {
        $v = (float) $this->total_price_vat_excl;
        return $this->is_credit_note ? -$v : $v;
    }

    public function getSignedTotalPriceAttribute(): float
    {
        $v = (float) $this->total_price;
        return $this->is_credit_note ? -$v : $v;
    }

    public function getSignedTotalPaidAttribute(): float
    {
        $v = (float) $this->total_paid;
        return $this->is_credit_note ? -$v : $v;
    }
}
