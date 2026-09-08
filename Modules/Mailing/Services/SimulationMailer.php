<?php

namespace Modules\Mailing\Services;

use App\Contracts\MarketingCampaignInterface;
use Illuminate\Support\Facades\Log;
use Modules\Mailing\Exceptions\MailConfigurationException;
use Modules\Prospects\Models\Prospect;

/**
 * CLA-532: explicit simulation transport for MarketingCampaignInterface.
 *
 * Selected by config('app.mailing_driver') === 'simulation'. Performs NO HTTP
 * and dispatches no e-mail. It is NOT a reuse of SaaSMailer (that stub is the
 * MAI-026 ESP placeholder and out of scope here).
 *
 * State / counter effects (dev & staging): returning true flows through
 * ExecuteCampaignJob::sendToProspects() exactly like a successful real send —
 * per-recipient CampaignMessage.status = 'sent', sent_count is incremented and
 * the campaign finishes COMPLETED with finished_at set. No dedicated
 * 'simulated' message status is introduced; the only distinguishing signal is
 * the '[simulation]' log line and the resolved class.
 *
 * Production guard: with APP_ENV=production this throws MailConfigurationException.
 * ExecuteCampaignJob catches it per-recipient (it extends RuntimeException), so
 * failed_count grows, every message is 'failed', sent_count stays 0 and the
 * campaign ends FAILED — never COMPLETED, never marked sent.
 */
class SimulationMailer implements MarketingCampaignInterface
{
    public function sendCampaign(
        Prospect $prospect,
        array $emails,
        string $subject,
        string $htmlBody,
        string $unsubscribeUrl,
        ?string $trackingToken = null,
        bool $isCommercial = true,
    ): bool {
        if (app()->environment('production')) {
            throw new MailConfigurationException(
                'SimulationMailer is not allowed in production (APP_ENV=production). '
                .'Set MAILING_DRIVER=microsoft-graph.'
            );
        }

        Log::info('[simulation] campaign send skipped — no e-mail dispatched', [
            'prospect_id' => $prospect->id,
            'emails' => $emails,
            'subject' => $subject,
            'commercial' => $isCommercial,
        ]);

        return true;
    }
}
