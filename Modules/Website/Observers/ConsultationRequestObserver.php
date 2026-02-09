<?php

namespace Modules\Website\Observers;

use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Services\ConsultationService;
use Illuminate\Support\Facades\Auth;

class ConsultationRequestObserver
{
    public function __construct(
        protected ConsultationService $service
    ) {}

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
            $oldUser = \App\Models\User::find($consultationRequest->getOriginal('assigned_to'))?->name ?? 'Nobody';
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
