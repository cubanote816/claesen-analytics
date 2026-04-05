<?php

namespace Modules\Prospects\Jobs;

use App\Contracts\MarketingCampaignInterface;
use Modules\Prospects\Models\Prospect;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteMailingCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $prospectIds, 
        public int $templateId, 
        public ?int $userId = null,
        public ?string $description = null
    ) {}

    public function handle(MarketingCampaignInterface $mailer): void
    {
        $template = \Modules\Mailing\Models\EmailTemplate::findOrFail($this->templateId);
        $prospects = Prospect::with(['locations', 'region'])->whereIn('id', $this->prospectIds)->get();

        if ($prospects->isEmpty()) {
            \Illuminate\Support\Facades\Log::warning("ExecuteMailingCampaignJob: No prospects found for provided IDs.", ['ids' => $this->prospectIds]);
            return;
        }

        // Create the Campaign record
        $campaign = \Modules\Prospects\Models\ProspectMailCampaign::create([
            'user_id' => $this->userId,
            'template_name' => $template->name,
            'description' => $this->description,
            'subject_snapshot' => $template->subject,
            'body_snapshot' => $template->body,
            'total_count' => $prospects->count(),
            'status' => 'processing',
        ]);

        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

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
                \Modules\Prospects\Models\ProspectMailLog::create([
                    'prospect_mail_campaign_id' => $campaign->id,
                    'prospect_id' => $prospect->id,
                    'user_id' => $this->userId,
                    'status' => 'skipped',
                    'template_name' => $template->name,
                    'sent_at' => now(),
                ]);
                $skippedCount++;
                $campaign->update(['skipped_count' => $skippedCount]);
                continue;
            }

            // Parse dynamic variables
            $parsedSubject = str_replace(
                ['{{ name }}', '{{ regio }}'],
                [$prospect->name, $prospect->region?->name ?? __('prospects::resource.defaults.region')],
                $template->subject
            );

            $parsedBody = str_replace(
                ['{{ name }}', '{{ regio }}'],
                ['<strong>' . $prospect->name . '</strong>', $prospect->region?->name ?? __('prospects::resource.defaults.region')],
                $template->body
            );

            $errorMessage = null;
            try {
                \Illuminate\Support\Facades\Log::info("Executing Prospect Mailer", [
                    'prospect_id' => $prospect->id,
                    'user_id' => $this->userId,
                    'campaign_id' => $campaign->id,
                ]);
                $isSuccess = $mailer->sendCampaign($prospect, $emails, $parsedSubject, $parsedBody);
            } catch (\Exception $e) {
                $isSuccess = false;
                $errorMessage = $e->getMessage();
                \Illuminate\Support\Facades\Log::error("Mailer Exception in Job", [
                    'error' => $errorMessage,
                    'prospect_id' => $prospect->id,
                    'campaign_id' => $campaign->id,
                ]);
            }

            if ($isSuccess) {
                $successCount++;
            } else {
                $failedCount++;
            }

            foreach ($emails as $recipientEmail) {
                \Modules\Prospects\Models\ProspectMailLog::create([
                    'prospect_mail_campaign_id' => $campaign->id,
                    'prospect_id' => $prospect->id,
                    'user_id' => $this->userId,
                    'email' => $recipientEmail,
                    'template_name' => $template->name,
                    'subject_snapshot' => $parsedSubject,
                    'body_snapshot' => $parsedBody,
                    'status' => $isSuccess ? 'sent' : 'failed',
                    'error_message' => $isSuccess ? null : ($errorMessage ?? __('prospects::resource.notifications.error_mailer')),
                    'sent_at' => now(),
                ]);
            }

            $campaign->update([
                'success_count' => $successCount,
                'failed_count' => $failedCount,
            ]);

            sleep(1);
        }

        $campaign->update([
            'status' => 'completed',
            'finished_at' => now(),
        ]);
    }
}
