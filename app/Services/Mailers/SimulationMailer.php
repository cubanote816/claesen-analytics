<?php

namespace App\Services\Mailers;

use App\Contracts\MarketingCampaignInterface;
use App\Mail\ProspectCampaignMail;
use App\Models\Prospect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SimulationMailer implements MarketingCampaignInterface
{
    public function sendCampaign(Prospect $prospect, array $emails, string $subject, string $htmlBody): bool
    {
        Log::info("Simulating email to: " . implode(',', $emails));

        try {
            Mail::to($emails)->send(new ProspectCampaignMail($prospect, $subject, $htmlBody));
            return true;
        } catch (\Exception $e) {
            Log::error("Simulation Mailer Error: " . $e->getMessage());
            return false;
        }
    }
}
