<?php

namespace App\Services\Mailers;

use App\Contracts\MarketingCampaignInterface;
use App\Models\Prospect;
use Illuminate\Support\Facades\Log;

class SaaSMailer implements MarketingCampaignInterface
{
    public function sendCampaign(Prospect $prospect, array $emails, string $subject, string $htmlBody): bool
    {
        Log::info("Sending to SaaS API for: " . implode(',', $emails));

        // Mocking HTTP SaaS Request
        try {
            // Http::post('https://api.mailchimp.com/...', [...])
            return true;
        } catch (\Exception $e) {
            Log::error("SaaS Mailer Error: " . $e->getMessage());
            return false;
        }
    }
}
