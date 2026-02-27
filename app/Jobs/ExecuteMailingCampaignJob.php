<?php

namespace App\Jobs;

use App\Contracts\MarketingCampaignInterface;
use App\Models\Prospect;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteMailingCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $prospectIds, public int $templateId) {}

    public function handle(MarketingCampaignInterface $mailer): void
    {
        $template = \App\Models\EmailTemplate::findOrFail($this->templateId);
        $prospects = Prospect::with('locations')->whereIn('id', $this->prospectIds)->get();

        foreach ($prospects as $prospect) {
            $emails = $prospect->locations
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->pluck('email')
                ->map(fn($emailList) => array_map('trim', explode(',', $emailList)))
                ->flatten()
                ->unique()
                ->toArray();

            if (empty($emails)) {
                continue;
            }

            // Parse dynamic variables
            $parsedSubject = str_replace(
                ['{{ name }}', '{{ regio }}'],
                [$prospect->name, $prospect->region ?? 'Vlaanderen'],
                $template->subject
            );

            $parsedBody = str_replace(
                ['{{ name }}', '{{ regio }}'],
                [$prospect->name, $prospect->region ?? 'Vlaanderen'],
                $template->body
            );

            $mailer->sendCampaign($prospect, $emails, $parsedSubject, $parsedBody);
            sleep(1);
        }
    }
}
