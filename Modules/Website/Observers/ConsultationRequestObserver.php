<?php

namespace Modules\Website\Observers;

use Modules\Core\Models\User;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Services\ConsultationService;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ConsultationRequestObserver
{
    public function __construct(
        protected ConsultationService $service
    ) {}

    /**
     * F4/CLA-477 of the multi-organization program — docs/ai/adr-multi-organization.md.
     *
     * Runs on every real save (the Filament edit form's own Eloquent
     * ->save() — ConsultationService::updateStatus() bypasses this via
     * updateQuietly() and does the equivalent inline, see its own
     * docblock).
     */
    public function saving(ConsultationRequest $consultationRequest): void
    {
        $statusBeforeThisSave = $consultationRequest->getOriginal('status') ?? ConsultationRequest::STATUS_NEW;

        if ($consultationRequest->isDirty('assigned_to') && $consultationRequest->assigned_to) {
            $this->assertAssigneeBelongsToSameOrganization($consultationRequest);

            // "Estados nuevo, asignado, ..." — a lead genuinely acquiring an
            // owner is what the 'assigned' state means; only auto-advances
            // out of 'new', never overrides a status someone already moved
            // forward by hand (in_progress/waiting_client/closed/spam). Must
            // run BEFORE the SLA stamp check below — it can itself be the
            // only reason status ends up dirty in this save (assigning a
            // 'new' lead with no other field touched).
            if ($consultationRequest->status === ConsultationRequest::STATUS_NEW) {
                $consultationRequest->status = ConsultationRequest::STATUS_ASSIGNED;
            }
        }

        if ($consultationRequest->isDirty('status')) {
            $consultationRequest->maybeStampFirstResponse($statusBeforeThisSave, $consultationRequest->status);
        }
    }

    /**
     * "Responsable debe pertenecer a la misma empresa" — gated by
     * config('organizations.enforce') (D4), same as every other
     * cross-organization check in this program: inert for Claesen today,
     * real the moment enforcement is on. Site has no BelongsToSite scope
     * of its own, so this resolves cleanly regardless of enforcement state.
     */
    private function assertAssigneeBelongsToSameOrganization(ConsultationRequest $consultationRequest): void
    {
        if (! config('organizations.enforce')) {
            return;
        }

        $site = $consultationRequest->site;
        $assignee = User::find($consultationRequest->assigned_to);

        if ($site && $assignee && $site->organization_id !== $assignee->organization_id) {
            throw new RuntimeException('Cannot assign a consultation request to a user outside its site\'s organization.');
        }
    }

    /**
     * Handle the ConsultationRequest "updated" event.
     */
    public function updated(ConsultationRequest $consultationRequest): void
    {
        $dirty = $consultationRequest->getDirty();
        $userId = Auth::id();

        // Track Status Change
        if (isset($dirty['status'])) {
            $oldStatus = $consultationRequest->getOriginal('status');
            $newStatus = $consultationRequest->status;

            $this->service->logActivity(
                $consultationRequest,
                'status_change',
                __('website.activities.logs.status_change', [
                    'old' => __("website.consultation_requests.status_options.{$oldStatus}"),
                    'new' => __("website.consultation_requests.status_options.{$newStatus}"),
                ]),
                ['old_value' => $oldStatus, 'new_value' => $newStatus],
                $userId
            );
        }

        // Track Priority Change
        if (isset($dirty['priority'])) {
            $oldPriority = $consultationRequest->getOriginal('priority') ?? 'none';
            $newPriority = $consultationRequest->priority;

            $this->service->logActivity(
                $consultationRequest,
                'priority_change',
                __('website.activities.logs.priority_change', [
                    'priority' => __("website.consultation_requests.priority_options.{$newPriority}"),
                ]),
                ['old_value' => $oldPriority, 'new_value' => $newPriority],
                $userId
            );
        }

        // Track Assignment Change
        if (isset($dirty['assigned_to'])) {
            $oldUser = \Modules\Core\Models\User::find($consultationRequest->getOriginal('assigned_to'))?->name ?? 'Nobody';
            $newUser = $consultationRequest->assignedUser?->name ?? 'Nobody';

            $this->service->logActivity(
                $consultationRequest,
                'assignment_change',
                __('website.activities.logs.assignment_change', ['user' => $newUser]),
                ['old_value' => $oldUser, 'new_value' => $newUser],
                $userId
            );
        }

        // Track Follow-up Date Change
        if (isset($dirty['follow_up_date'])) {
            $oldDate = $consultationRequest->getOriginal('follow_up_date');
            $newDate = $consultationRequest->follow_up_date;

            $this->service->logActivity(
                $consultationRequest,
                'follow_up_update',
                __('website.activities.logs.follow_up_update', ['date' => $newDate?->format('d/m/Y') ?? 'none']),
                ['old_value' => $oldDate, 'new_value' => $newDate],
                $userId
            );
        }

        // Track Internal Notes Change
        if (isset($dirty['internal_notes']) && !empty($consultationRequest->internal_notes)) {
            $this->service->logActivity(
                $consultationRequest,
                'comment',
                __('website.activities.logs.comment'),
                [],
                $userId
            );
        }
    }
}
