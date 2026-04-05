<?php

namespace Modules\Mailing\Services;

use App\Contracts\MarketingCampaignInterface;
use Modules\Mailing\Emails\ProspectCampaignMail;
use Modules\Prospects\Models\Prospect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SimulationMailer implements MarketingCampaignInterface
{
    public function sendCampaign(Prospect $prospect, array $emails, string $subject, string $htmlBody): bool
    {
        Log::info("Simulating email to: " . implode(',', $emails));
        \Illuminate\Support\Facades\Mail::mailer('microsoft-graph')->to($emails)->send(new ProspectCampaignMail($prospect, $subject, $htmlBody));
        return true;
    }
}
