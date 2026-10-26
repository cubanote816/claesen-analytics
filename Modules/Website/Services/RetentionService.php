<?php

namespace Modules\Website\Services;

use Modules\Core\Models\Site;
use Modules\Website\Models\ConsultationRequest;

/**
 * F4/CLA-476 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "Política por estado y tipo de dato": spam leads (no legitimate business
 * value) get a short retention and a real hard delete; closed leads keep
 * their aggregate/reporting fields but have their PII scrubbed after a
 * longer window. Both windows resolve per-organization
 * (Organization::retentionSetting(), D7) with a global config('website.
 * retention.*') fallback — every organization has retention_policy => null
 * today, so this is byte-for-byte the global default until one is given
 * its own override.
 *
 * Used by both Modules\Website\Console\Commands\ApplyRetentionPolicyCommand
 * (age-based, automated) and ConsultationRequestResource's on-demand
 * "Erase" action (right-to-erasure request, any age) — the manual path
 * calls eraseNow() directly, bypassing the age check entirely.
 */
class RetentionService
{
    public function spamRetentionDays(Site $site): int
    {
        return (int) ($site->organization?->retentionSetting('spam_days')
            ?? config('website.retention.spam_days'));
    }

    public function closedAnonymizeDays(Site $site): int
    {
        return (int) ($site->organization?->retentionSetting('closed_anonymize_days')
            ?? config('website.retention.closed_anonymize_days'));
    }

    /**
     * @return array{deleted: int, anonymized: int}
     */
    public function applyToSite(Site $site, bool $dryRun): array
    {
        $spamCutoff = now()->subDays($this->spamRetentionDays($site));
        $closedCutoff = now()->subDays($this->closedAnonymizeDays($site));

        // withoutGlobalScope('site'): this runs from a scheduled command,
        // outside any HTTP request or authenticated user — OrganizationContext
        // has nothing to resolve a site from. Same pattern already
        // established for TriggerStaticSiteRebuildJob/PublicationState::
        // current() (CLA-472) — the explicit where('site_id', ...) below
        // already does the scoping BelongsToSite's global scope would.
        $spamQuery = ConsultationRequest::query()
            ->withoutGlobalScope('site')
            ->where('site_id', $site->id)
            ->where('status', ConsultationRequest::STATUS_SPAM)
            ->where('created_at', '<', $spamCutoff);

        $closedQuery = ConsultationRequest::query()
            ->withoutGlobalScope('site')
            ->where('site_id', $site->id)
            ->where('status', ConsultationRequest::STATUS_CLOSED)
            ->whereNull('anonymized_at')
            ->where('updated_at', '<', $closedCutoff);

        $deletedCount = (clone $spamQuery)->count();
        $anonymizedCount = (clone $closedQuery)->count();

        if ($dryRun) {
            return ['deleted' => $deletedCount, 'anonymized' => $anonymizedCount];
        }

        foreach ($closedQuery->get() as $consultation) {
            $consultation->anonymize();
            $this->logRetentionAction($consultation, 'anonymized');
        }

        // Deletion is destructive and irreversible — log before, not after
        // (a row logged after its own deletion would have nothing left to
        // performedOn()).
        foreach ((clone $spamQuery)->get() as $consultation) {
            $this->logRetentionAction($consultation, 'deleted (spam retention expired)');
        }
        $spamQuery->delete();

        return ['deleted' => $deletedCount, 'anonymized' => $anonymizedCount];
    }

    /**
     * On-demand GDPR erasure (right to be forgotten) — an admin's manual
     * "Erase" action, any lead age, regardless of the automated policy's
     * cutoffs or config('website.retention.enabled').
     */
    public function eraseNow(ConsultationRequest $consultation, int|string|null $causerId): void
    {
        $consultation->anonymize();

        activity()
            ->causedBy($causerId)
            ->performedOn($consultation)
            ->log('Erased on demand (GDPR right-to-erasure request)');
    }

    private function logRetentionAction(ConsultationRequest $consultation, string $action): void
    {
        activity()
            ->performedOn($consultation)
            ->log("Retention policy: {$action}");
    }
}
