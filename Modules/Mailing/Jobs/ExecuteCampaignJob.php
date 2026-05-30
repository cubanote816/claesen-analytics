<?php

namespace Modules\Mailing\Jobs;

use App\Contracts\MarketingCampaignInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Models\TrackedLink;
use Modules\Mailing\Services\SuppressionService;
use Modules\Prospects\Models\Prospect;

class ExecuteCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array    $prospectIds  Used only when $campaignId is null (inline path).
     * @param  int      $templateId   Used only when $campaignId is null (inline path).
     * @param  int|null $userId       Optional actor; used for audit in both paths.
     * @param  string|null $description  Inline path only.
     * @param  int|null $campaignId   When set: execute an existing pre-approved Campaign record.
     *                                $prospectIds and $templateId are ignored.
     */
    public function __construct(
        public array $prospectIds = [],
        public int $templateId = 0,
        public ?int $userId = null,
        public ?string $description = null,
        public ?int $campaignId = null,
    ) {}

    public function handle(MarketingCampaignInterface $mailer, SuppressionService $suppression): void
    {
        $originalLocale = App::getLocale();

        try {
            if ($this->campaignId !== null) {
                $this->executeExistingCampaign($mailer, $suppression);
            } else {
                $this->executeInlineCampaign($mailer, $suppression);
            }
        } finally {
            App::setLocale($originalLocale);
        }
    }

    /**
     * Called by the queue system when the job throws and exhausts retries.
     * Sets the campaign to FAILED so it is visible in the dashboard.
     */
    public function failed(\Throwable $exception): void
    {
        if ($this->campaignId === null) {
            return;
        }

        $updated = Campaign::where('id', $this->campaignId)
            ->where('status', CampaignStatus::SENDING->value)
            ->update(['status' => CampaignStatus::FAILED->value]);

        if ($updated) {
            Log::error('ExecuteCampaignJob: job failed — campaign set to failed', [
                'campaign_id' => $this->campaignId,
                'error'       => $exception->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Scheduled campaign path (campaignId provided)
    // -------------------------------------------------------------------------

    private function executeExistingCampaign(MarketingCampaignInterface $mailer, SuppressionService $suppression): void
    {
        $campaign = Campaign::findOrFail($this->campaignId);

        if (! in_array($campaign->status, [CampaignStatus::APPROVED, CampaignStatus::SENDING], true)) {
            throw new \DomainException(
                "Campaign #{$campaign->id} cannot be sent: status is '{$campaign->status->value}'. Expected 'approved' or 'sending'."
            );
        }

        // If still APPROVED here (direct dispatch bypassing command), do atomic claim.
        if ($campaign->status === CampaignStatus::APPROVED) {
            $claimed = Campaign::where('id', $campaign->id)
                ->where('status', CampaignStatus::APPROVED->value)
                ->update(['status' => CampaignStatus::SENDING->value]);

            if ($claimed === 0) {
                Log::info('ExecuteCampaignJob: campaign claimed by another process — aborting.', [
                    'campaign_id' => $campaign->id,
                ]);
                return;
            }
        }

        $template    = EmailTemplate::findOrFail($campaign->template_id);
        $prospectIds = $campaign->resolveAudience();

        if (empty($prospectIds)) {
            Campaign::where('id', $campaign->id)->update([
                'status'      => CampaignStatus::COMPLETED->value,
                'finished_at' => now(),
            ]);
            return;
        }

        $allProspects = Prospect::with(['locations', 'region'])
            ->whereIn('id', $prospectIds)
            ->get();

        $campaign->update(['total_count' => $allProspects->count()]);

        $this->sendToProspects($campaign, $template, $allProspects, $mailer, $suppression);
    }

    // -------------------------------------------------------------------------
    // Inline/legacy path (campaignId not provided — Filament bulk action)
    // -------------------------------------------------------------------------

    private function executeInlineCampaign(MarketingCampaignInterface $mailer, SuppressionService $suppression): void
    {
        $template = EmailTemplate::findOrFail($this->templateId);

        $allProspects = Prospect::with(['locations', 'region'])
            ->whereIn('id', $this->prospectIds)
            ->get();

        if ($allProspects->isEmpty()) {
            Log::warning('ExecuteCampaignJob: No prospects found.', ['ids' => $this->prospectIds]);
            return;
        }

        // Inline dispatch from Filament bulk action — treated as pre-approved.
        $campaign = Campaign::create([
            'created_by'       => $this->userId,
            'template_name'    => $template->name,
            'description'      => $this->description,
            'subject_snapshot' => $template->subject,
            'body_snapshot'    => $template->body,
            'total_count'      => $allProspects->count(),
            'status'           => CampaignStatus::SENDING,
        ]);

        $this->sendToProspects($campaign, $template, $allProspects, $mailer, $suppression);
    }

    // -------------------------------------------------------------------------
    // Shared send loop
    // -------------------------------------------------------------------------

    private function sendToProspects(
        Campaign $campaign,
        EmailTemplate $template,
        Collection $allProspects,
        MarketingCampaignInterface $mailer,
        SuppressionService $suppression,
    ): void {
        $sentCount    = 0;
        $failedCount  = 0;
        $skippedCount = 0;

        foreach ($allProspects as $prospect) {
            App::setLocale($prospect->language ?? 'nl');

            $emails = $prospect->locations
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->pluck('email')
                ->map(fn ($emailList) => array_map('trim', explode(',', $emailList)))
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
                    'campaign_id'   => $campaign->id,
                    'prospect_id'   => $prospect->id,
                    'user_id'       => $this->userId,
                    'email'         => 'no-email@claesen.be',
                    'status'        => 'skipped',
                    'template_name' => $template->name,
                    'sent_at'       => now(),
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

            $trackingToken = Str::random(64);

            $trackedBody  = $this->rewriteLinksForTracking($parsedBody, $campaign, $trackingToken);
            $pixelUrl     = route('mailing.track.open', $trackingToken) . '.gif';
            $trackedBody .= "\n" . '<img src="' . $pixelUrl . '" width="1" height="1" alt="" style="display:none;">';

            $errorMessage = null;
            try {
                Log::info('ExecuteCampaignJob: sending', [
                    'prospect_id' => $prospect->id,
                    'campaign_id' => $campaign->id,
                ]);
                $isSuccess = $mailer->sendCampaign($prospect, $emails, $parsedSubject, $trackedBody, $unsubscribeUrl, $trackingToken);
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
                    'tracking_token'   => $trackingToken,
                    'sent_at'          => now(),
                ]);
            }

            $campaign->update([
                'sent_count'   => $sentCount,
                'failed_count' => $failedCount,
            ]);

            usleep(config('mailing.send_delay_ms', 500) * 1000);
        }

        $campaign->update([
            'status'      => CampaignStatus::COMPLETED,
            'finished_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Link rewriting
    // -------------------------------------------------------------------------

    private function rewriteLinksForTracking(string $html, Campaign $campaign, string $trackingToken): string
    {
        return preg_replace_callback(
            '/\bhref=(["\'])(https?:\/\/[^"\']+)\1/i',
            function (array $m) use ($campaign, $trackingToken): string {
                $quote       = $m[1];
                $originalUrl = $m[2];

                if (str_contains($originalUrl, 'unsubscribe') || str_contains($originalUrl, 'afmelden')) {
                    return $m[0];
                }

                $hash = substr(md5((string) $campaign->id . $originalUrl), 0, 12);

                TrackedLink::firstOrCreate(
                    ['campaign_id' => $campaign->id, 'hash' => $hash],
                    ['original_url' => $originalUrl, 'created_at' => now()]
                );

                $trackingUrl = route('mailing.track.click', [$trackingToken, $hash]);

                return "href={$quote}{$trackingUrl}{$quote}";
            },
            $html
        );
    }
}
