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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Mailing\Enums\AudienceType;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\TemplateCategory;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\ContactPreference;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Models\TrackedLink;
use Modules\Mailing\Services\MicrosoftGraphService;
use Modules\Mailing\Services\SuppressionService;
use Modules\Mailing\Support\CampaignFinishedNotifier;
use Modules\Prospects\Models\Prospect;

/**
 * Inline runs are chained: each run sends until its time budget is spent, advances the campaign's
 * run_generation and dispatches a successor carrying the new generation. Only the run whose
 * generation equals the campaign's may send, so replayed or stale deliveries exit without effect.
 *
 * Known, accepted limit: a crash between Graph accepting a send and the message row being written
 * can resend that one address on the next run.
 */
class ExecuteCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Below the redis retry_after (90 s) so a running job is never redelivered mid-run.
     * Worst case: 20 s budget + 30 s Graph + 15 s OAuth + 2 s delay + startup < 85 s.
     */
    public $timeout = 85;

    /** Identifies this run for the claim on mailing_campaigns.run_owner; stable across queue retries. */
    public ?string $runId = null;

    /** CLA-681: one Graph retry window for the whole run when no run budget applies (existing / A/B runs). */
    private ?float $retryDeadline = null;

    /** Worker locale before the send loop switches to each prospect's language; used for the finish notice. */
    private ?string $notificationLocale = null;

    /**
     * @param  array       $prospectIds   Used only when $campaignId is null (inline path).
     * @param  int         $templateId    Used only when $campaignId is null (inline path).
     * @param  int|null    $userId        Optional actor; used for audit in both paths.
     * @param  string|null $description   Inline path only.
     * @param  int|null    $campaignId    When set: execute an existing Campaign record.
     *                                    $prospectIds and $templateId are ignored.
     * @param  bool        $isWinnerSend        A/B: dispatch remaining with the winning variant subject.
     * @param  array|null  $overrideProspectIds Follow-up: exact audience provided by DispatchFollowUpsCommand.
     *                                          When present, resolveAudience() is NOT called — prevents
     *                                          sending to more prospects than intended.
     * @param  string|null $dispatchKey     Idempotency key for the inline path. Generated once in the
     *                                      constructor so it is serialized with the job and identical
     *                                      across retries; stored on mailing_campaigns.dispatch_key.
     * @param  int|null    $generation      Inline chain position (null = first dispatch = 0); must equal
     *                                      mailing_campaigns.run_generation for the run to proceed.
     */
    public function __construct(
        public array $prospectIds = [],
        public int $templateId = 0,
        public ?int $userId = null,
        public ?string $description = null,
        public ?int $campaignId = null,
        public bool $isWinnerSend = false,
        public ?array $overrideProspectIds = null,
        public ?string $dispatchKey = null,
        public ?int $generation = null,
    ) {
        $this->dispatchKey ??= (string) Str::uuid();
        $this->runId = (string) Str::uuid();
    }

    /** Payloads serialized before these properties existed leave them uninitialized. */
    private function normalizeLegacyPayload(): void
    {
        $this->dispatchKey ??= $this->job?->uuid() ?? (string) Str::uuid();
        $this->runId       ??= $this->job?->uuid() ?? (string) Str::uuid();
        $this->generation  ??= 0;
    }

    public function handle(MarketingCampaignInterface $mailer, SuppressionService $suppression): void
    {
        // The time budget is measured from here, before the audience is loaded.
        $startedAt      = microtime(true);
        $this->retryDeadline = $startedAt + (float) config('mailing.graph_retry_window_seconds', 20);
        $this->normalizeLegacyPayload();
        $originalLocale = App::getLocale();
        $this->notificationLocale = $originalLocale;

        try {
            if ($this->campaignId !== null) {
                $this->executeExistingCampaign($mailer, $suppression);
            } else {
                $this->executeInlineCampaign($mailer, $suppression, $startedAt);
            }
        } finally {
            App::setLocale($originalLocale);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->normalizeLegacyPayload();

        // Inline path: only the current generation may close the campaign.
        $query = $this->campaignId !== null
            ? Campaign::where('id', $this->campaignId)
            : Campaign::where('dispatch_key', $this->dispatchKey)
                ->where('run_generation', $this->generation ?? 0);

        $updated = $query
            ->where('status', CampaignStatus::SENDING->value)
            ->update(['status' => CampaignStatus::FAILED->value, 'finished_at' => now()]);

        if ($updated) {
            Log::error('ExecuteCampaignJob: job failed — campaign set to failed', [
                'campaign_id'    => $this->campaignId,
                'dispatch_key'   => $this->dispatchKey,
                'is_winner_send' => $this->isWinnerSend,
                'error'          => $exception->getMessage(),
            ]);
            $this->notifyFinished($this->campaignId !== null
                ? Campaign::find($this->campaignId)
                : Campaign::where('dispatch_key', $this->dispatchKey)->first());
        } elseif ($this->campaignId === null) {
            $this->recoverOrphanedHandover();
        }
    }

    /**
     * The handover (generation G+1, unowned) committed but the successor push failed on this run's
     * last attempt. Publish it once more; if the queue is still down, close the campaign.
     */
    private function recoverOrphanedHandover(): void
    {
        $orphaned = fn () => Campaign::where('dispatch_key', $this->dispatchKey)
            ->where('status', CampaignStatus::SENDING->value)
            ->where('run_generation', ($this->generation ?? 0) + 1)
            ->whereNull('run_owner');

        if (! $orphaned()->exists()) {
            return;
        }

        try {
            $this->dispatchSuccessor(($this->generation ?? 0) + 1);
        } catch (\Throwable $e) {
            if ($orphaned()->update(['status' => CampaignStatus::FAILED->value, 'finished_at' => now()])) {
                Log::error('ExecuteCampaignJob: successor could not be published — campaign set to failed', [
                    'dispatch_key' => $this->dispatchKey,
                    'error'        => $e->getMessage(),
                ]);
                $this->notifyFinished(Campaign::where('dispatch_key', $this->dispatchKey)->first());
            }
        }
    }

    /** Called only by the code path whose update actually moved the campaign to its final state. */
    private function notifyFinished(?Campaign $campaign): void
    {
        if ($campaign !== null) {
            CampaignFinishedNotifier::notify($campaign, $this->userId, $this->notificationLocale);
        }
    }

    // -------------------------------------------------------------------------
    // Snapshot guard — shared by all existing-campaign paths
    // -------------------------------------------------------------------------

    /**
     * Validates that the campaign's category snapshots form an unambiguous, valid combination.
     * Fails the campaign and throws DomainException on any violation.
     *
     * Combinations:
     *  - commercial + valid preference key  → allowed, enforcement active
     *  - commercial + null/invalid pref     → reject
     *  - transactional + null               → allowed, no enforcement
     *  - transactional + non-null pref      → reject (stale/inconsistent data)
     *  - null/unknown category              → reject (pre-deploy campaign without backfill)
     */
    private function assertValidSnapshots(Campaign $campaign): void
    {
        $knownCategories = [TemplateCategory::COMMERCIAL->value, TemplateCategory::TRANSACTIONAL->value];
        $validPrefKeys   = array_keys(config('mailing.preference_categories', []));
        $templateCat     = $campaign->template_category_snapshot;
        $prefCat         = $campaign->preference_category_snapshot;

        if (! in_array($templateCat, $knownCategories, true)) {
            $this->failCampaign($campaign);
            throw new \DomainException(
                "Campaign #{$campaign->id}: null or unknown template_category_snapshot '{$templateCat}'. "
                . 'Run mailing:backfill-preference-snapshots before enabling workers.'
            );
        }

        if ($templateCat === TemplateCategory::COMMERCIAL->value
            && ! in_array($prefCat, $validPrefKeys, true)) {
            $this->failCampaign($campaign);
            throw new \DomainException(
                "Campaign #{$campaign->id}: commercial campaign has invalid preference_category_snapshot "
                . "'{$prefCat}'. Valid: " . implode(', ', $validPrefKeys) . '.'
            );
        }

        if ($templateCat === TemplateCategory::TRANSACTIONAL->value && $prefCat !== null) {
            $this->failCampaign($campaign);
            throw new \DomainException(
                "Campaign #{$campaign->id}: transactional campaign has unexpected preference_category_snapshot "
                . "'{$prefCat}'. This indicates inconsistent snapshot data."
            );
        }
    }

    private function failCampaign(Campaign $campaign): void
    {
        $failed = Campaign::where('id', $campaign->id)
            ->whereIn('status', [CampaignStatus::APPROVED->value, CampaignStatus::SENDING->value])
            ->update(['status' => CampaignStatus::FAILED->value]);

        if ($failed === 1) {
            $this->notifyFinished($campaign);
        }
    }

    // -------------------------------------------------------------------------
    // Scheduled campaign path (campaignId provided)
    // -------------------------------------------------------------------------

    private function executeExistingCampaign(MarketingCampaignInterface $mailer, SuppressionService $suppression): void
    {
        $campaign = Campaign::findOrFail($this->campaignId);

        // Fail-closed: validate snapshots before any work, regardless of when the campaign was created
        $this->assertValidSnapshots($campaign);

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

        $prospectIds = $this->overrideProspectIds ?? $campaign->resolveAudience();

        if (empty($prospectIds)) {
            $this->completeEmptyCampaign($campaign);
            return;
        }

        $prospects = Prospect::with(['locations', 'region'])->whereIn('id', $prospectIds)->get();
        $campaign->update(['total_count' => $prospects->count()]);

        $this->sendToProspects($campaign, $prospects, $mailer, $suppression,
            subjectOverride: $campaign->subject_snapshot);
    }

    private function completeEmptyCampaign(Campaign $campaign): void
    {
        $completed = Campaign::where('id', $campaign->id)
            ->where('status', CampaignStatus::SENDING->value)
            ->update([
                'status'      => CampaignStatus::COMPLETED->value,
                'finished_at' => now(),
            ]);

        if ($completed === 1) {
            $this->notifyFinished($campaign);
        }
    }

    // -------------------------------------------------------------------------
    // A/B test — first pass
    // -------------------------------------------------------------------------

    private function executeAbTestFirstPass(Campaign $campaign, MarketingCampaignInterface $mailer, SuppressionService $suppression): void
    {
        if ($campaign->status === CampaignStatus::APPROVED) {
            $claimed = Campaign::where('id', $campaign->id)
                ->where('status', CampaignStatus::APPROVED->value)
                ->update(['status' => CampaignStatus::SENDING->value]);

            if ($claimed === 0) {
                Log::info('ExecuteCampaignJob: A/B campaign already claimed — aborting first pass.', ['campaign_id' => $campaign->id]);
                return;
            }
        }

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

        $fullIds    = $campaign->resolveAudience();
        $totalCount = count($fullIds);

        $campaign->update(['total_count' => $totalCount]);

        if ($totalCount < 2) {
            Log::warning('ExecuteCampaignJob: audience too small for A/B test — falling back to normal send.', [
                'campaign_id' => $campaign->id,
                'count'       => $totalCount,
            ]);
            $prospects = Prospect::with(['locations', 'region'])->whereIn('id', $fullIds)->get();
            $this->sendToProspects($campaign, $prospects, $mailer, $suppression,
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

        $this->sendToProspects($campaign, $prospectsA, $mailer, $suppression,
            abVariant: 'A', subjectOverride: $campaign->subject_snapshot, completeAfter: false);

        $this->sendToProspects($campaign, $prospectsB, $mailer, $suppression,
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

        $winnerSubject = $campaign->ab_winner_variant === 'A'
            ? $campaign->subject_snapshot
            : $campaign->ab_subject_b;

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
            $this->completeEmptyCampaign($campaign);
            return;
        }

        $remaining = Prospect::with(['locations', 'region'])->whereIn('id', $remainingIds)->get();

        $this->sendToProspects($campaign, $remaining, $mailer, $suppression,
            abVariant: null, subjectOverride: $winnerSubject);
    }

    // -------------------------------------------------------------------------
    // Inline/legacy path (campaignId not provided — Filament bulk action / FAB)
    // -------------------------------------------------------------------------

    /** @return array<int, int> */
    private function audienceProspectIds(): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $this->prospectIds),
            fn (int $id) => $id > 0
        )));
    }

    private function executeInlineCampaign(MarketingCampaignInterface $mailer, SuppressionService $suppression, float $startedAt): void
    {
        $template = EmailTemplate::findOrFail($this->templateId);
        $snapshot = Campaign::buildSnapshotFrom($template);

        // Fail-closed before creating any campaign record
        $validPrefKeys = array_keys(config('mailing.preference_categories', []));
        if ($snapshot['template_category_snapshot'] === TemplateCategory::COMMERCIAL->value
            && ! in_array($snapshot['preference_category_snapshot'], $validPrefKeys, true)) {
            throw new \DomainException(
                "Inline campaign: commercial template '{$template->name}' has no valid preference_category. "
                . 'Classify the template in Filament before running campaigns.'
            );
        }

        $allProspects = Prospect::with(['locations', 'region'])
            ->whereIn('id', $this->prospectIds)
            ->get();

        if ($allProspects->isEmpty()) {
            Log::warning('ExecuteCampaignJob: No prospects found.', ['ids' => $this->prospectIds]);
            return;
        }

        // Retries and continuations reuse the campaign created by the first run.
        $campaign = Campaign::where('dispatch_key', $this->dispatchKey)->first()
            ?? Campaign::create([
                'created_by'       => $this->userId,
                'description'      => $this->description,
                'total_count'      => $allProspects->count(),
                'status'           => CampaignStatus::SENDING,
                'dispatch_key'     => $this->dispatchKey,
                'run_generation'   => 0,
                'audience_type'    => AudienceType::MANUAL,
                'audience_filters' => ['prospect_ids' => $this->audienceProspectIds()],
                ...$snapshot,
            ]);

        $generation = $this->generation ?? 0;

        // Claim: only one run may own a generation; the same runId may re-claim (queue retry).
        // Decide by re-reading the row: MySQL reports 0 affected rows when run_owner already
        // holds this runId, so the update count cannot tell a retry from a stale run.
        if ($campaign->status === CampaignStatus::SENDING) {
            Campaign::where('id', $campaign->id)
                ->where('run_generation', $generation)
                ->where(fn ($q) => $q->whereNull('run_owner')->orWhere('run_owner', $this->runId))
                ->update(['run_owner' => $this->runId]);
            $campaign->refresh();
        }

        $claimed = $campaign->status === CampaignStatus::SENDING
            && $campaign->run_generation === $generation
            && $campaign->run_owner === $this->runId;

        if (! $claimed) {
            // Handover committed (G+1, unowned) but its successor is not running: re-publish it.
            // Duplicates are harmless because only one successor can claim.
            if ($campaign->status === CampaignStatus::SENDING
                && $campaign->run_generation === $generation + 1
                && $campaign->run_owner === null) {
                $this->dispatchSuccessor($generation + 1);
            }

            Log::info('ExecuteCampaignJob: stale or closed inline run — exiting without sending.', [
                'campaign_id'  => $campaign->id,
                'dispatch_key' => $this->dispatchKey,
                'generation'   => $generation,
                'current'      => $campaign->run_generation,
            ]);
            return;
        }

        $budgetExhausted = $this->sendToProspects($campaign, $allProspects, $mailer, $suppression,
            deadline: $startedAt + (int) config('mailing.run_budget_seconds', 20));

        if ($budgetExhausted) {
            // Release the generation and publish the successor only after the update commits,
            // so a fast worker never sees the old generation.
            DB::transaction(function () use ($campaign, $generation): void {
                $advanced = Campaign::where('id', $campaign->id)
                    ->where('run_generation', $generation)
                    ->where('run_owner', $this->runId)
                    ->update(['run_generation' => $generation + 1, 'run_owner' => null]);

                if ($advanced === 1) {
                    $this->dispatchSuccessor($generation + 1);
                }
            });
        }
    }

    private function dispatchSuccessor(int $generation): void
    {
        self::dispatch(
            prospectIds: $this->prospectIds,
            templateId:  $this->templateId,
            userId:      $this->userId,
            description: $this->description,
            dispatchKey: $this->dispatchKey,
            generation:  $generation,
        )->afterCommit();
    }

    // -------------------------------------------------------------------------
    // Shared send loop — uses campaign snapshots exclusively, never live template
    // -------------------------------------------------------------------------

    private function sendToProspects(
        Campaign $campaign,
        Collection $allProspects,
        MarketingCampaignInterface $mailer,
        SuppressionService $suppression,
        ?string $abVariant = null,
        ?string $subjectOverride = null,
        bool $completeAfter = true,
        ?float $deadline = null,
    ): bool {
        // $attempted counts real writes (sends and new skip rows) so every run makes progress
        // before it honours the deadline; idempotent no-ops do not count.
        $attempted = 0;
        $exhausted = function () use (&$attempted, $deadline): bool {
            return $deadline !== null && $attempted > 0 && microtime(true) >= $deadline;
        };

        // All content must come from immutable campaign snapshots
        $subjectTemplate = $subjectOverride ?? $campaign->subject_snapshot;

        if (empty($subjectTemplate)) {
            throw new \DomainException("Campaign #{$campaign->id}: subject_snapshot is required but missing.");
        }
        if (empty($campaign->body_snapshot)) {
            throw new \DomainException("Campaign #{$campaign->id}: body_snapshot is required but missing.");
        }

        $isCommercial = $campaign->template_category_snapshot === TemplateCategory::COMMERCIAL->value;

        // Pre-load category opt-outs in one query (1 query, not N+1 — uses index on category+subscribed)
        $prefCategory = $campaign->preference_category_snapshot;
        $optedOutIds  = [];
        if ($isCommercial && $prefCategory !== null) {
            $optedOutIds = ContactPreference::where('category', $prefCategory)
                ->where('subscribed', false)
                ->whereIn('prospect_id', $allProspects->pluck('id'))
                ->pluck('prospect_id')
                ->flip()
                ->toArray();
        }

        $sentCount   = 0;
        $failedCount = 0;

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

            // 1. Global unsubscribe
            if ($prospect->unsubscribed_at !== null) {
                if ($exhausted()) {
                    return true;
                }
                $attempted += (int) $this->recordSkip($campaign, [
                    'campaign_id'   => $campaign->id,
                    'prospect_id'   => $prospect->id,
                    'user_id'       => $this->userId,
                    'email'         => $primaryEmail,
                    'status'        => 'unsubscribed',
                    'template_name' => $campaign->template_name,
                    'error_message' => __('prospects::resource.options.status.unsubscribed'),
                    'ab_variant'    => $abVariant,
                    'sent_at'       => now(),
                ], perAddress: false);
                continue;
            }

            // 2. Category preference opt-out (commercial only). A suppressed primary address
            // keeps precedence and is recorded with its suppression reason.
            if (isset($optedOutIds[$prospect->id])) {
                if ($suppression->isSuppressed($primaryEmail)) {
                    if ($exhausted()) {
                        return true;
                    }
                    $attempted += (int) $this->recordSuppressedSkip($campaign, $prospect, $primaryEmail, $suppression, $abVariant);
                    continue;
                }

                if ($exhausted()) {
                    return true;
                }
                $attempted += (int) $this->recordSkip($campaign, [
                    'campaign_id'   => $campaign->id,
                    'prospect_id'   => $prospect->id,
                    'user_id'       => $this->userId,
                    'email'         => $primaryEmail,
                    'status'        => 'skipped',
                    'template_name' => $campaign->template_name,
                    'error_message' => 'category_opted_out:' . $prefCategory,
                    'ab_variant'    => $abVariant,
                    'sent_at'       => now(),
                ], perAddress: false);
                continue;
            }

            // 3. No email address
            if (empty($emails)) {
                if ($exhausted()) {
                    return true;
                }
                $attempted += (int) $this->recordSkip($campaign, [
                    'campaign_id'   => $campaign->id,
                    'prospect_id'   => $prospect->id,
                    'user_id'       => $this->userId,
                    'email'         => 'no-email@claesen.be',
                    'status'        => 'skipped',
                    'template_name' => $campaign->template_name,
                    'ab_variant'    => $abVariant,
                    'sent_at'       => now(),
                ], perAddress: false);
                continue;
            }

            // 4. Send (one message per address; suppression is checked per address)
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
                $campaign->body_snapshot
            );

            foreach ($emails as $recipientEmail) {
                // Retry/continuation safety: an address that already has an outcome in this
                // campaign (delivered or failed) is never sent again.
                if (CampaignMessage::where('campaign_id', $campaign->id)
                    ->where('email', $recipientEmail)
                    ->whereIn('status', ['sent', 'failed'])
                    ->exists()) {
                    continue;
                }

                // Suppression list (hard bounce / spam — global prohibition), per address
                if ($suppression->isSuppressed($recipientEmail)) {
                    if ($exhausted()) {
                        return true;
                    }
                    $attempted += (int) $this->recordSuppressedSkip($campaign, $prospect, $recipientEmail, $suppression, $abVariant);
                    continue;
                }

                // Time budget: always make progress (one send per run), then stop starting new sends.
                if ($exhausted()) {
                    return true;
                }
                $attempted++;

                // Each recipient gets its own token for the open pixel and click links.
                $trackingToken = Str::random(64);

                $trackedBody  = $this->rewriteLinksForTracking($parsedBody, $campaign, $trackingToken);
                $pixelUrl     = $this->trackingUrl('mailing.track.open', $trackingToken) . '.gif';
                $trackedBody .= "\n" . '<img src="' . $pixelUrl . '" width="1" height="1" alt="" style="display:none;">';

                $errorMessage = null;
                try {
                    Log::info('ExecuteCampaignJob: sending', [
                        'prospect_id'   => $prospect->id,
                        'campaign_id'   => $campaign->id,
                        'ab_variant'    => $abVariant,
                        'is_commercial' => $isCommercial,
                    ]);
                    // CLA-681: Graph transient retries must fit the run budget, or the run-wide
                    // retry window, so retries across many messages cannot outlive the job timeout.
                    MicrosoftGraphService::setSendDeadline($deadline ?? $this->retryDeadline);
                    $isSuccess = $mailer->sendCampaign(
                        $prospect, [$recipientEmail], $parsedSubject, $trackedBody,
                        $unsubscribeUrl, $trackingToken, $isCommercial
                    );
                } catch (\Throwable $e) {
                    $isSuccess    = false;
                    $errorMessage = $e->getMessage();
                    Log::error('ExecuteCampaignJob: mailer exception', [
                        'error'       => $errorMessage,
                        'prospect_id' => $prospect->id,
                        'campaign_id' => $campaign->id,
                    ]);
                } finally {
                    MicrosoftGraphService::setSendDeadline(null);
                }

                // Message row and counter move together so counters always equal the rows.
                DB::transaction(function () use ($campaign, $prospect, $recipientEmail, $parsedSubject, $parsedBody, $isSuccess, $errorMessage, $trackingToken, $abVariant): void {
                    CampaignMessage::create([
                        'campaign_id'      => $campaign->id,
                        'prospect_id'      => $prospect->id,
                        'user_id'          => $this->userId,
                        'email'            => $recipientEmail,
                        'template_name'    => $campaign->template_name,
                        'subject_snapshot' => $parsedSubject,
                        'body_snapshot'    => $parsedBody,
                        'status'           => $isSuccess ? 'sent' : 'failed',
                        'error_message'    => $isSuccess ? null : ($errorMessage ?? __('prospects::resource.notifications.error_mailer')),
                        'tracking_token'   => $trackingToken,
                        'ab_variant'       => $abVariant,
                        'sent_at'          => now(),
                    ]);
                    $campaign->increment($isSuccess ? 'sent_count' : 'failed_count');
                });

                $isSuccess ? $sentCount++ : $failedCount++;

                // Exchange Online allows 30 messages/minute per mailbox: pace every recipient send.
                usleep(config('mailing.send_delay_ms', 2000) * 1000);
            }
        }

        if ($completeAfter) {
            // Chained (deadline) runs judge the whole campaign, not just this run.
            if ($deadline !== null) {
                $campaign->refresh();
                $sentCount   = $campaign->sent_count;
                $failedCount = $campaign->failed_count;
            }

            $finalStatus = ($sentCount === 0 && $failedCount > 0)
                ? CampaignStatus::FAILED
                : CampaignStatus::COMPLETED;

            $transitioned = Campaign::where('id', $campaign->id)
                ->where('status', CampaignStatus::SENDING->value)
                ->update([
                    'status'      => $finalStatus->value,
                    'finished_at' => now(),
                ]);

            if ($transitioned === 1) {
                $this->notifyFinished($campaign);
            } elseif (Campaign::whereKey($campaign->id)->where('status', CampaignStatus::FAILED->value)->exists()) {
                // Race kept as before CLA-677: the failed() hook closed the run while this
                // execution was still sending; the computed outcome wins. No second notice:
                // failed() already sent the run's single notification (S2).
                Campaign::whereKey($campaign->id)->update([
                    'status'      => $finalStatus->value,
                    'finished_at' => now(),
                ]);
            }
        }

        return false;
    }

    private function recordSuppressedSkip(
        Campaign $campaign,
        Prospect $prospect,
        string $email,
        SuppressionService $suppression,
        ?string $abVariant,
    ): bool {
        $reason = $suppression->getReason($email)?->value ?? 'suppressed';

        return $this->recordSkip($campaign, [
            'campaign_id'   => $campaign->id,
            'prospect_id'   => $prospect->id,
            'user_id'       => $this->userId,
            'email'         => $email,
            'status'        => $reason === 'unsubscribed' ? 'unsubscribed' : 'skipped',
            'template_name' => $campaign->template_name,
            'error_message' => 'suppressed: ' . $reason,
            'ab_variant'    => $abVariant,
            'sent_at'       => now(),
        ], perAddress: true);
    }

    /**
     * Writes a skip row and bumps skipped_count atomically. Idempotent per campaign + prospect
     * (+ address for per-address skips), so retried or continued runs never duplicate it.
     */
    private function recordSkip(Campaign $campaign, array $attributes, bool $perAddress): bool
    {
        $existing = CampaignMessage::where('campaign_id', $campaign->id)
            ->where('prospect_id', $attributes['prospect_id'])
            ->whereIn('status', ['skipped', 'unsubscribed']);

        if ($perAddress) {
            $existing->where('email', $attributes['email']);
        }

        if ($existing->exists()) {
            return false;
        }

        DB::transaction(function () use ($campaign, $attributes): void {
            CampaignMessage::create($attributes);
            $campaign->increment('skipped_count');
        });

        return true;
    }

    // -------------------------------------------------------------------------
    // Link rewriting
    // -------------------------------------------------------------------------

    /**
     * Build an absolute tracking URL on the public tracking domain.
     *
     * Open-pixel and click links must be reachable by recipients outside the
     * local network, so they use mailing.tracking_domain (public) instead of
     * route()'s absolute host (APP_URL, which is the internal backoffice). The
     * route path is generated relative and prefixed with the configured base;
     * when tracking_domain is empty it falls back to APP_URL (current behaviour).
     *
     * @param  string|array<int, string>  $parameters
     */
    private function trackingUrl(string $routeName, string|array $parameters): string
    {
        $base = config('mailing.tracking_domain') ?: config('app.url');

        return rtrim((string) $base, '/') . route($routeName, $parameters, absolute: false);
    }

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

                $trackingUrl = $this->trackingUrl('mailing.track.click', [$trackingToken, $hash]);

                return "href={$quote}{$trackingUrl}{$quote}";
            },
            $html
        );
    }
}
