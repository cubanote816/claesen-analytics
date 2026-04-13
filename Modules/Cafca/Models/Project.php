<?php

namespace Modules\Cafca\Models;

use Modules\Performance\Models\ProjectInsight;
use Modules\Core\Traits\ReadOnlyTrait;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends CafcaModel
{
    use ReadOnlyTrait;

    protected $table = 'project';
    protected $primaryKey = 'id';
 
    protected $casts = [
        'estimated_total_hours_to_execute' => 'float',
        'fl_active' => 'boolean',
    ];
 
    public function insight(): HasOne
    {
        return $this->hasOne(ProjectInsight::class, 'project_id', 'id');
    }

    /**
     * Relationship with the manager (Employee).
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(LegacyEmployee::class, 'yard_manager', 'id');
    }
 
    /**
     * Relationship with labor logs.
     */
    public function labor(): HasMany
    {
        return $this->hasMany(Labor::class, 'project_id', 'id');
    }

    /**
     * Relationship with invoices.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'project_id', 'id');
    }

    /**
     * Relationship with project estimates.
     */
    public function estimates(): HasMany
    {
        return $this->hasMany(ProjectEstimate::class, 'project_id', 'id');
    }

    /**
     * Accessor for project type name.
     */
    public function getProjectTypeNameAttribute(): string
    {
        $categoryMap = [
            0 => 'Industrie',
            1 => 'Industrie',
            2 => 'Openbare Verlichting',
            3 => 'Openbare Verlichting',
            4 => 'Sportverlichting',
            5 => 'Sportverlichting',
            6 => 'Masten',
            7 => 'Industrie',
            8 => 'Algemeen',
        ];

        return $categoryMap[$this->project_type] ?? 'Onbekend';
    }

    /**
     * Labor summary aggregated by employee.
     */
    public function getLaborSummaryAttribute()
    {
        return $this->labor()
            ->select('employee_id')
            ->selectRaw('SUM(hours) as total_hours')
            ->groupBy('employee_id')
            ->with('employee')
            ->get()
            ->map(fn($item) => (object) [
                'employee_id' => trim($item->employee_id),
                'name' => $item->employee?->name ?? 'Unknown',
                'hours' => number_format($item->total_hours, 2),
            ]);
    }

    /**
     * Total worked hours on project.
     */
    public function getTotalWorkedHoursAttribute(): float
    {
        return (float) $this->labor()->sum('hours');
    }

    /**
     * Planned execution hours.
     */
    public function getPlannedHoursAttribute(): float
    {
        // Try to get aggregated hours from the detailed estimate items
        // project -> project_estimates -> estimate_item
        $detailedPlanned = $this->estimates()
            ->with('items')
            ->get()
            ->flatMap(fn($est) => $est->items)
            ->sum('total_hours');

        if ($detailedPlanned > 0) {
            return (float) $detailedPlanned;
        }

        // Fallback to the legacy aggregated field if details are missing
        return (float) ($this->estimated_total_hours_to_execute ?? 0);
    }

    /**
     * Efficiency calculation.
     */
    public function getTimeEfficiencyAttribute(): float
    {
        $planned = $this->planned_hours;
        if ($planned <= 0) {
            return 0;
        }

        return round(($this->total_worked_hours / $planned) * 100, 1);
    }

    /**
     * Get the number of pending invoices.
     */
    public function getPendingInvoicesCountAttribute(): int
    {
        return $this->invoices()
            ->get()
            ->filter(fn($invoice) => $invoice->is_pending)
            ->count();
    }

    /**
     * Get the list of pending invoices.
     */
    public function getPendingInvoicesAttribute()
    {
        return $this->invoices()
            ->get()
            ->filter(fn($invoice) => $invoice->is_pending);
    }

    /**
     * Total amount invoiced for this project.
     */
    public function getTotalInvoicedAmountAttribute(): float
    {
        return (float) $this->invoices()->sum('total_price');
    }

    /**
     * Total amount actually paid by the client.
     */
    public function getTotalPaidAmountAttribute(): float
    {
        return (float) $this->invoices()->sum('total_paid');
    }

    /**
     * Unpaid balance (Debt).
     */
    public function getPendingDebtAmountAttribute(): float
    {
        return max(0, $this->total_invoiced_amount - $this->total_paid_amount);
    }

    /**
     * Amount yet to be invoiced (Unbilled).
     */
    public function getToBeInvoicedAmountAttribute(): float
    {
        $contract = (float) $this->contract_price;
        if ($contract <= 0) {
            return 0;
        }

        return max(0, $contract - $this->total_invoiced_amount);
    }

    /**
     * Check if there are any pending (unpaid) invoices.
     */
    public function getHasPendingInvoicesAttribute(): bool
    {
        return $this->invoices()->get()->some->is_pending;
    }
}
