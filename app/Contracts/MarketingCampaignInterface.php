<?php

namespace App\Contracts;

use Modules\Prospects\Models\Prospect;

interface MarketingCampaignInterface
{
    public function sendCampaign(Prospect $prospect, array $emails, string $subject, string $htmlBody): bool;
}
