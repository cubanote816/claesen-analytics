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

    /**
     * Check if the invoice is a credit note.
     */
    public function getIsCreditNoteAttribute(): bool
    {
        return str_starts_with($this->id, 'CN');
    }

    /**
     * Check if the invoice has a pending balance.
     */
    public function getIsPendingAttribute(): bool
    {
        // Don't treat credit notes as "unpaid debt" in the traditional sense for the watchdog
        if ($this->is_credit_note) {
            return false;
        }

        // Using a small epsilon for float comparison safety
        return ($this->total_price - $this->total_paid) > 0.01;
    }

    /**
     * Get the remaining balance to be paid.
     */
    public function getBalanceAttribute(): float
    {
        return (float) ($this->total_price - $this->total_paid);
    }
}
