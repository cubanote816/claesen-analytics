<?php

namespace Modules\Intelligence\Console\Commands;

use Illuminate\Console\Command;
use Modules\Intelligence\Models\BillingAlert;

/**
 * BI-2B-UX-10 — One-shot command to dismiss existing partial_payment alerts.
 *
 * The partial_payment rule has been deactivated. Non-expired partially-paid
 * invoices generate noise without requiring immediate action; once they expire
 * they are caught by detectOverdueReceivables. This command marks all
 * non-terminal partial_payment alerts as dismissed so they no longer appear
 * in the active sections of the Billing Control page.
 *
 * Run once after deploying BI-2B-UX-10:
 *   php artisan intelligence:dismiss-partial-payment-alerts
 */
class DismissPartialPaymentAlerts extends Command
{
    protected $signature   = 'intelligence:dismiss-partial-payment-alerts
                              {--dry-run : Preview affected rows without writing}';

    protected $description = 'Dismiss all existing partial_payment alerts (rule deactivated in BI-2B-UX-10)';

    private const RESOLUTION_NOTE =
        'Regel gedeactiveerd — gedeeltelijke betalingen worden niet langer apart opgevolgd. '
        . 'Indien de factuur vervalt, wordt ze opgevolgd via Vervallen vordering.';

    public function handle(): int
    {
        $query = BillingAlert::where('alert_type', 'partial_payment')
            ->whereNotIn('status', [BillingAlert::STATUS_DISMISSED, BillingAlert::STATUS_RESOLVED]);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No active partial_payment alerts found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} active partial_payment alert(s).");

        if ($this->option('dry-run')) {
            $this->warn('[dry-run] No changes written.');

            return self::SUCCESS;
        }

        $updated = $query->update([
            'status'           => BillingAlert::STATUS_DISMISSED,
            'resolution_notes' => self::RESOLUTION_NOTE,
        ]);

        $this->info("Dismissed {$updated} partial_payment alert(s).");

        return self::SUCCESS;
    }
}
