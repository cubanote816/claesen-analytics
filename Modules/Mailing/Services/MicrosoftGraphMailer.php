<?php

namespace Modules\Mailing\Services;

use App\Contracts\MarketingCampaignInterface;
use Modules\Mailing\Emails\ProspectCampaignMail;
use Modules\Prospects\Models\Prospect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MicrosoftGraphMailer implements MarketingCampaignInterface
{
    public function sendCampaign(Prospect $prospect, array $emails, string $subject, string $htmlBody, string $unsubscribeUrl): bool
    {
        Log::info("Sending email via Microsoft Graph for: " . implode(',', $emails));

        try {
            Mail::mailer('microsoft-graph')
                ->to($emails)
                ->send(new ProspectCampaignMail($prospect, $subject, $htmlBody, $unsubscribeUrl));
            return true;
        } catch (\Exception $e) {
            Log::error("Microsoft Graph Mailer Error: " . $e->getMessage());
            return false;
        }
    }
}
