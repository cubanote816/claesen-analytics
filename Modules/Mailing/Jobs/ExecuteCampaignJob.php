<?php

namespace Modules\Mailing\Jobs;

use App\Contracts\MarketingCampaignInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Services\SuppressionService;
use Modules\Prospects\Models\Prospect;

class ExecuteCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $prospectIds,
        public int $templateId,
        public ?int $userId = null,
        public ?string $description = null
    ) {}

    public function handle(MarketingCampaignInterface $mailer, SuppressionService $suppression): void
    {
        $template = \Modules\Mailing\Models\EmailTemplate::findOrFail($this->templateId);
        $originalLocale = App::getLocale();

        try {
            $allProspects = Prospect::with(['locations', 'region'])
                ->whereIn('id', $this->prospectIds)
                ->get();

            if ($allProspects->isEmpty()) {
                Log::warning('ExecuteCampaignJob: No prospects found.', ['ids' => $this->prospectIds]);
                return;
            }

            // Inline dispatch from Filament bulk action — treated as pre-approved.
            // The full approval workflow (draft→review→approved) is enforced in MAI-018.
            $campaign = Campaign::create([
                'created_by'       => $this->userId,
                'template_name'    => $template->name,
                'description'      => $this->description,
                'subject_snapshot' => $template->subject,
                'body_snapshot'    => $template->body,
                'total_count'      => $allProspects->count(),
                'status'           => CampaignStatus::SENDING,
            ]);

            $sentCount    = 0;
            $failedCount  = 0;
            $skippedCount = 0;

            foreach ($allProspects as $prospect) {
                App::setLocale($prospect->language ?? 'nl');

                $emails = $prospect->locations
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->pluck('email')
                    ->map(fn($emailList) => array_map('trim', explode(',', $emailList)))
                    ->flatten()
                    ->unique()
                    ->toArray();

                $primaryEmail = $emails[0] ?? 'no-email@claesen.be';

                if ($prospect->unsubscribed_at !== null) {
                    CampaignMessage::create([
                        'campaign_id'   => $campaign->id,
                        'prospect_id'   => $prospect->id,
                        'user_id'       => $this->userId,
                        'email'         => $primaryEmail,
                        'status'        => 'skipped',
                        'template_name' => $template->name,
                        'error_message' => __('prospects::resource.options.status.unsubscribed'),
                        'sent_at'       => now(),
                    ]);
                    $campaign->increment('skipped_count');
                    continue;
                }

                if ($suppression->isSuppressed($primaryEmail)) {
                    $reason = $suppression->getReason($primaryEmail)?->value ?? 'suppressed';
                    CampaignMessage::create([
                        'campaign_id'   => $campaign->id,
                        'prospect_id'   => $prospect->id,
                        'user_id'       => $this->userId,
                        'email'         => $primaryEmail,
                        'status'        => 'skipped',
                        'template_name' => $template->name,
                        'error_message' => 'suppressed: ' . $reason,
                        'sent_at'       => now(),
                    ]);
                    $campaign->increment('skipped_count');
                    continue;
                }

                if (empty($emails)) {
                    CampaignMessage::create([
                        'campaign_id'  => $campaign->id,
                        'prospect_id'  => $prospect->id,
                        'user_id'      => $this->userId,
                        'email'        => 'no-email@claesen.be',
                        'status'       => 'skipped',
                        'template_name' => $template->name,
                        'sent_at'      => now(),
                    ]);
                    $campaign->increment('skipped_count');
                    continue;
                }

                $unsubscribeUrl = sprintf(
                    'https://claesen-verlichting.be/afmelden/?p=%s&t=%s&l=%s',
                    $prospect->id,
                    $prospect->getUnsubscribeToken(),
                    $prospect->language ?? 'nl'
                );

                $parsedSubject = str_replace(
                    ['{{ name }}', '{{ regio }}', '{{ unsubscribe_url }}'],
                    [$prospect->name, $prospect->region?->name ?? __('prospects::resource.defaults.region'), $unsubscribeUrl],
                    $template->subject
                );

                $parsedBody = str_replace(
                    ['{{ name }}', '{{ regio }}', '{{ unsubscribe_url }}'],
                    ['<strong>' . $prospect->name . '</strong>', $prospect->region?->name ?? __('prospects::resource.defaults.region'), $unsubscribeUrl],
                    $template->body
                );

                $errorMessage = null;
                try {
                    Log::info('ExecuteCampaignJob: sending', [
                        'prospect_id' => $prospect->id,
                        'campaign_id' => $campaign->id,
                    ]);
                    $isSuccess = $mailer->sendCampaign($prospect, $emails, $parsedSubject, $parsedBody, $unsubscribeUrl);
                } catch (\Exception $e) {
                    $isSuccess    = false;
                    $errorMessage = $e->getMessage();
                    Log::error('ExecuteCampaignJob: mailer exception', [
                        'error'       => $errorMessage,
                        'prospect_id' => $prospect->id,
                        'campaign_id' => $campaign->id,
                    ]);
                }

                $isSuccess ? $sentCount++ : $failedCount++;

                foreach ($emails as $recipientEmail) {
                    CampaignMessage::create([
                        'campaign_id'      => $campaign->id,
                        'prospect_id'      => $prospect->id,
                        'user_id'          => $this->userId,
                        'email'            => $recipientEmail,
                        'template_name'    => $template->name,
                        'subject_snapshot' => $parsedSubject,
                        'body_snapshot'    => $parsedBody,
                        'status'           => $isSuccess ? 'sent' : 'failed',
                        'error_message'    => $isSuccess ? null : ($errorMessage ?? __('prospects::resource.notifications.error_mailer')),
                        'sent_at'          => now(),
                    ]);
                }

                $campaign->update([
                    'sent_count'   => $sentCount,
                    'failed_count' => $failedCount,
                ]);

                // Throttle: configurable, default 500 ms — no sleep() blocking the worker
                usleep(config('mailing.send_delay_ms', 500) * 1000);
            }

            $campaign->update([
                'status'      => CampaignStatus::COMPLETED,
                'finished_at' => now(),
            ]);
        } finally {
            App::setLocale($originalLocale);
        }
    }
}
