<?php

namespace App\Contracts;

use App\Models\Prospect;

interface MarketingCampaignInterface
{
    public function sendCampaign(Prospect $prospect, array $emails, string $subject, string $htmlBody): bool;
}
