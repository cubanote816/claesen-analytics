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
     * @param  array       $prospectIds   Used only when $campaignId is null (inline path).
     * @param  int         $templateId    Used only when $campaignId is null (inline path).
     * @param  int|null    $userId        Optional actor; used for audit in both paths.
     * @param  string|null $description   Inline path only.
     * @param  int|null    $campaignId    When set: execute an existing Campaign record.
     *                                    $prospectIds and $templateId are ignored.
     * @param  bool        $isWinnerSend  A/B: dispatch remaining with the winning variant subject.
     */
    public function __construct(
        public array $prospectIds = [],
        public int $templateId = 0,
        public ?int $userId = null,
        public ?string $description = null,
        public ?int $campaignId = null,
        public bool $isWinnerSend = false,
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
                'campaign_id'    => $this->campaignId,
                'is_winner_send' => $this->isWinnerSend,
                'error'          => $exception->getMessage(),
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

        if ($campaign->isAbTest()) {
            if ($this->isWinnerSend) {
                $this->executeAbWinnerSend($campaign, $mailer, $suppression);
            } else {
                $this->executeAbTestFirstPass($campaign, $mailer, $suppression);
            }
            return;
        }

        // Normal scheduled campaign (no A/B)
        if ($campaign->status === CampaignStatus::APPROVED) {
            $claimed = Campaign::where('id', $campaign->id)
                ->where('status', CampaignStatus::APPROVED->value)
                ->update(['status' => CampaignStatus::SENDING->value]);

            if ($claimed === 0) {
                Log::info('ExecuteCampaignJob: campaign already claimed — aborting.', ['campaign_id' => $campaign->id]);
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

        $prospects = Prospect::with(['locations', 'region'])->whereIn('id', $prospectIds)->get();
        $campaign->update(['total_count' => $prospects->count()]);

        $this->sendToProspects($campaign, $template, $prospects, $mailer, $suppression,
            subjectOverride: $campaign->subject_snapshot);
    }

    // -------------------------------------------------------------------------
    // A/B test — first pass
    // -------------------------------------------------------------------------

    private function executeAbTestFirstPass(Campaign $campaign, MarketingCampaignInterface $mailer, SuppressionService $suppression): void
    {
        // Claim status APPROVED → SENDING
        if ($campaign->status === CampaignStatus::APPROVED) {
            $claimed = Campaign::where('id', $campaign->id)
                ->where('status', CampaignStatus::APPROVED->value)
                ->update(['status' => CampaignStatus::SENDING->value]);

            if ($claimed === 0) {
                Log::info('ExecuteCampaignJob: A/B campaign already claimed — aborting first pass.', ['campaign_id' => $campaign->id]);
                return;
            }
        }

        // Atomic claim of the A/B test start (idempotency guard against retries)
        $claimedAb = Campaign::where('id', $campaign->id)
            ->whereNull('ab_test_started_at')
            ->update(['ab_test_started_at' => now()]);

        if ($claimedAb === 0) {
            Log::info('ExecuteCampaignJob: A/B test already started — skipping first pass.', ['campaign_id' => $campaign->id]);
            return;
        }

        $splitPercent = (int) ($campaign->ab_split_percent ?? 10);
        if ($splitPercent < 1 || $splitPercent > 50) {
            throw new \InvalidArgumentException(
                "Campaign #{$campaign->id}: ab_split_percent must be between 1 and 50. Got: {$splitPercent}."
            );
        }

        $template   = EmailTemplate::findOrFail($campaign->template_id);
        $fullIds    = $campaign->resolveAudience();
        $totalCount = count($fullIds);

        $campaign->update(['total_count' => $totalCount]);

        // Audience too small — fall back to normal send with variant A subject
        if ($totalCount < 2) {
            Log::warning('ExecuteCampaignJob: audience too small for A/B test — falling back to normal send.', [
                'campaign_id' => $campaign->id,
                'count'       => $totalCount,
            ]);
            $prospects = Prospect::with(['locations', 'region'])->whereIn('id', $fullIds)->get();
            $this->sendToProspects($campaign, $template, $prospects, $mailer, $suppression,
                subjectOverride: $campaign->subject_snapshot);
            return;
        }

        $testSize = max(1, (int) ceil($totalCount * $splitPercent / 100));
        if ($testSize * 2 > $totalCount) {
            $testSize = (int) floor($totalCount / 2);
        }

        $groupAIds = array_slice($fullIds, 0, $testSize);
        $groupBIds = array_slice($fullIds, $testSize, $testSize);

        if (empty($groupAIds) || empty($groupBIds)) {
            throw new \DomainException(
                "Campaign #{$campaign->id}: A/B test group is empty after split (total: {$totalCount}, split: {$splitPercent}%)."
            );
        }

        $prospectsA = Prospect::with(['locations', 'region'])->whereIn('id', $groupAIds)->get();
        $prospectsB = Prospect::with(['locations', 'region'])->whereIn('id', $groupBIds)->get();

        // Variant A: subject_snapshot (does NOT complete campaign)
        $this->sendToProspects($campaign, $template, $prospectsA, $mailer, $suppression,
            abVariant: 'A', subjectOverride: $campaign->subject_snapshot, completeAfter: false);

        // Variant B: ab_subject_b (does NOT complete campaign)
        $this->sendToProspects($campaign, $template, $prospectsB, $mailer, $suppression,
            abVariant: 'B', subjectOverride: $campaign->ab_subject_b, completeAfter: false);

        Log::info('ExecuteCampaignJob: A/B test first pass complete — campaign awaiting winner selection.', [
            'campaign_id'  => $campaign->id,
            'group_a_size' => count($groupAIds),
            'group_b_size' => count($groupBIds),
            'remaining'    => $totalCount - count($groupAIds) - count($groupBIds),
        ]);
    }

    // -------------------------------------------------------------------------
    // A/B test — winner send (second pass)
    // -------------------------------------------------------------------------

    private function executeAbWinnerSend(Campaign $campaign, MarketingCampaignInterface $mailer, SuppressionService $suppression): void
    {
        if ($campaign->ab_winner_variant === null) {
            throw new \DomainException(
                "Campaign #{$campaign->id}: winner send requested but ab_winner_variant is not set."
            );
        }

        $template = EmailTemplate::findOrFail($campaign->template_id);

        $winnerSubject = $campaign->ab_winner_variant === 'A'
            ? $campaign->subject_snapshot
            : $campaign->ab_subject_b;

        // Remaining = full audience − already sent (naturally idempotent)
        $alreadySentIds = CampaignMessage::where('campaign_id', $campaign->id)
            ->whereNotNull('prospect_id')
            ->pluck('prospect_id')
            ->toArray();

        $fullAudience = $campaign->resolveAudience();
        $remainingIds = array_values(array_diff($fullAudience, $alreadySentIds));

        if (empty($remainingIds)) {
            Log::info('ExecuteCampaignJob: no remaining prospects for winner send — completing campaign.', [
                'campaign_id' => $campaign->id,
            ]);
            Campaign::where('id', $campaign->id)->update([
                'status'      => CampaignStatus::COMPLETED->value,
                'finished_at' => now(),
            ]);
            return;
        }

        $remaining = Prospect::with(['locations', 'region'])->whereIn('id', $remainingIds)->get();

        $this->sendToProspects($campaign, $template, $remaining, $mailer, $suppression,
            abVariant: null, subjectOverride: $winnerSubject);
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
        ?string $abVariant = null,
        ?string $subjectOverride = null,
        bool $completeAfter = true,
    ): void {
        $sentCount    = 0;
        $failedCount  = 0;

        $subjectTemplate = $subjectOverride ?? $template->subject;

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
                    'ab_variant'    => $abVariant,
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
                    'ab_variant'    => $abVariant,
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
                    'ab_variant'    => $abVariant,
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
                $subjectTemplate
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
                    'ab_variant'  => $abVariant,
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
                    'ab_variant'       => $abVariant,
                    'sent_at'          => now(),
                ]);
            }

            $campaign->update([
                'sent_count'   => $campaign->sent_count + $sentCount,
                'failed_count' => $campaign->failed_count + $failedCount,
            ]);

            usleep(config('mailing.send_delay_ms', 500) * 1000);
        }

        if ($completeAfter) {
            $campaign->update([
                'status'      => CampaignStatus::COMPLETED,
                'finished_at' => now(),
            ]);
        }
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
