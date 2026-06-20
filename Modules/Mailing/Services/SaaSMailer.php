<?php

namespace Modules\Mailing\Services;

use App\Contracts\MarketingCampaignInterface;
use Modules\Prospects\Models\Prospect;
use Illuminate\Support\Facades\Log;

class SaaSMailer implements MarketingCampaignInterface
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
        Log::info("Sending to SaaS API for: " . implode(',', $emails));

        // Stub — ESP integration pending MAI-026
        try {
            // Http::post('https://api.mailchimp.com/...', [
            //    'unsubscribe_url' => $unsubscribeUrl,
            //    'is_commercial'   => $isCommercial,
            //    ...
            // ])
            return true;
        } catch (\Exception $e) {
            Log::error("SaaS Mailer Error: " . $e->getMessage());
            return false;
        }
    }
}
