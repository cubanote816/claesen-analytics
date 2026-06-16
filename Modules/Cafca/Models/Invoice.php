<?php

namespace Modules\Cafca\Models;

use Modules\Core\Traits\ReadOnlyTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends CafcaModel
{
    use ReadOnlyTrait;

    protected $table = 'invoice';
    protected $primaryKey = 'id';

    /**
     * Relationship with project.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

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
        return str_starts_with($this->id, 'CN');
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

    public function getIsPendingAttribute(): bool
    {
        if ($this->is_credit_note) {
            return false;
        }

        return ($this->total_price - $this->total_paid) > 0.01;
    }

    public function getBalanceAttribute(): float
    {
        return (float) ($this->total_price - $this->total_paid);
    }
}
