<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Models\User;
use Modules\Knx\Mail\KnxNoticeMail;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxNotificationPreference;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxVisit;

/**
 * The email side of a notice (KNX-54).
 *
 * Recipients are always the project lead plus the configurable office mailbox: the lead
 * because they own the project, the shared box because a notice must not depend on one
 * person being at their desk.
 *
 * The immediate/immediate decision is a product decision from 07-10-2026: only
 * `issue_reported`, `malfunction_reported` and a `final` visit closure are mailed as
 * they happen. Everything else accumulates and goes out as a digest — at visit close,
 * or hourly while the visit is still open (the scheduler decides the cadence; this
 * service only answers "what is still un-emailed").
 *
 * `emailed_at` on the row is what makes it idempotent: a field retry creates no second
 * row, and a second run of the digest finds nothing to send.
 */
class KnxNoticeMailer
{
    /** The kinds that are mailed the moment they happen. */
    public const IMMEDIATE_KINDS = [
        KnxNotification::KIND_ISSUE_REPORTED,
        KnxNotification::KIND_MALFUNCTION_REPORTED,
    ];

    public function isImmediate(KnxNotification $notification): bool
    {
        if (in_array($notification->kind, self::IMMEDIATE_KINDS, true)) {
            return true;
        }

        if ($notification->kind === KnxNotification::KIND_VISIT_CLOSED) {
            // Only the FINAL acceptance is urgent: an end-of-visit note or a partial
            // delivery is office reading, not an alarm.
            return KnxVisit::query()->whereKey($notification->target_id)->value('type') === 'final';
        }

        return false;
    }

    /**
     * Mail one immediate notice. Returns the addresses it went to (empty when the only
     * readers turned it off and there is no office box configured).
     *
     * @return list<string>
     */
    public function sendImmediate(KnxNotification $notification): array
    {
        $project = $notification->project;

        if ($project === null) {
            return [];
        }

        $addresses = $this->recipients($project, immediate: true);

        if ($addresses === []) {
            return [];
        }

        Mail::to($addresses)->send(new KnxNoticeMail(collect([$notification]), immediate: true));

        // Marked as emailed even when only the office box got it: the notice has been
        // told, and the digest must not repeat it.
        $notification->forceFill(['emailed_at' => now()])->save();

        return $addresses;
    }

    /**
     * Mail everything still un-emailed of a project as one digest. Returns how many
     * notices it covered.
     */
    public function sendDigest(KnxProject $project): int
    {
        $notices = KnxNotification::query()
            ->where('project_id', $project->getKey())
            ->whereNull('emailed_at')
            ->whereNotIn('kind', self::IMMEDIATE_KINDS)
            ->orderBy('reported_at')
            ->with(['project', 'board', 'reportedBy'])
            ->get()
            // A `final` closure is immediate, so it does not belong here.
            ->reject(fn (KnxNotification $notification): bool => $this->isImmediate($notification));

        if ($notices->isEmpty()) {
            return 0;
        }

        $addresses = $this->recipients($project, immediate: false);

        if ($addresses !== []) {
            Mail::to($addresses)->send(new KnxNoticeMail($notices, immediate: false));
        }

        KnxNotification::query()->whereKey($notices->pluck('id'))->update(['emailed_at' => now()]);

        return $notices->count();
    }

    /**
     * The lead (when their own preference allows that kind of mail) plus the office
     * mailbox. The shared box always receives; a personal inbox is a choice.
     *
     * @return list<string>
     */
    private function recipients(KnxProject $project, bool $immediate): array
    {
        $addresses = [];

        $lead = $project->lead;
        $leadUser = $lead?->user;

        if ($leadUser instanceof User && $this->leadWantsMail($lead, $immediate)) {
            $addresses[] = $leadUser->email;
        }

        $mailbox = config('knx.office_mailbox');

        if (is_string($mailbox) && trim($mailbox) !== '') {
            $addresses[] = trim($mailbox);
        }

        return array_values(array_unique($addresses));
    }

    private function leadWantsMail(?KnxEmployee $lead, bool $immediate): bool
    {
        if ($lead === null) {
            return false;
        }

        $preference = KnxNotificationPreference::forEmployee($lead);

        return $immediate ? $preference->email_immediate : $preference->email_digest !== KnxNotificationPreference::DIGEST_OFF;
    }

    /**
     * @return Collection<int, KnxNotification>
     */
    public function pendingDigestFor(KnxProject $project): Collection
    {
        return KnxNotification::query()
            ->where('project_id', $project->getKey())
            ->whereNull('emailed_at')
            ->orderBy('reported_at')
            ->get()
            ->reject(fn (KnxNotification $notification): bool => $this->isImmediate($notification))
            ->values();
    }
}
