<?php

namespace Modules\Mailing\Services;

use App\Contracts\MarketingCampaignInterface;
use Modules\Mailing\Emails\ProspectCampaignMail;
use Modules\Mailing\Exceptions\MailDeliveryException;
use Modules\Prospects\Models\Prospect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MicrosoftGraphMailer implements MarketingCampaignInterface
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
        Log::info("Sending email via Microsoft Graph for: " . implode(',', $emails));

        try {
            Mail::mailer('microsoft-graph')
                ->to($emails)
                ->send(new ProspectCampaignMail($prospect, $subject, $htmlBody, $unsubscribeUrl, $trackingToken, $isCommercial));
            return true;
        } catch (MailDeliveryException $e) {
            // CLA-681: rethrown so the job stores this exact reason instead of the generic text.
            Log::error("Microsoft Graph Mailer Error: " . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            Log::error("Microsoft Graph Mailer Error: " . $e->getMessage());
            return false;
        }
    }
}
