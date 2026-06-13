<?php

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Model;

class BillingAlert extends Model
{
    protected $table = 'intelligence_billing_alerts';

    public const STATUS_OPEN      = 'open';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_RESOLVED  = 'resolved';

    public const TYPE_MISSING_CUSTOMER_INVOICE = 'missing_customer_invoice';
    public const TYPE_OVERDUE_RECEIVABLE       = 'overdue_receivable';
    public const TYPE_UNBILLED_FOLLOWUP_COST   = 'unbilled_followup_cost';
    public const TYPE_CLOSED_WITH_BALANCE      = 'closed_with_balance';

    protected $fillable = [
        'dedup_key',
        'period_year',
        'period_month',
        'alert_type',
        'severity',
        'status',
        'project_id',
        'relation_id',
        'invoice_id',
        'amount_activity_cost',
        'amount_estimated',
        'amount_open',
        'evidence_json',
        'recommendation',
        'ai_analysis',
        'assigned_to',
        'reviewed_by',
        'reviewed_at',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'amount_activity_cost' => 'decimal:2',
        'amount_estimated'     => 'decimal:2',
        'amount_open'          => 'decimal:2',
        'evidence_json'        => 'array',
        'reviewed_at'          => 'datetime',
        'resolved_at'          => 'datetime',
    ];

    /**
     * Build the deterministic dedup key. NULL ids become empty strings so the
     * UNIQUE index always applies (MySQL ignores NULLs in unique constraints).
     *
     * Format: "{year}:{month}:{alert_type}:{project_id|''}:{invoice_id|''}"
     */
    public static function buildDedupKey(
        int $year,
        int $month,
        string $alertType,
        ?string $projectId = null,
        ?string $invoiceId = null,
    ): string {
        return sprintf(
            '%d:%02d:%s:%s:%s',
            $year,
            $month,
            $alertType,
            $projectId ?? '',
            $invoiceId ?? '',
        );
    }
}
