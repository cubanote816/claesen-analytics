<?php

namespace Modules\Website\Services;

use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\ConsultationActivity;
use Illuminate\Support\Facades\DB;
use Exception;

class ConsultationService
{
    /**
     * Create a new consultation request from public form.
     */
    public function createRequest(array $data): ConsultationRequest
    {
        return DB::transaction(function () use ($data) {
            $request = ConsultationRequest::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'company' => $data['company'] ?? null,
                'message' => $data['message'],
                'type' => $data['type'] ?? 'consultation',
                'project_type' => $data['project_type'] ?? null,
                'source' => $data['source'] ?? 'website',
                'status' => 'pending',
                'last_activity_at' => now(),
            ]);

            $this->logActivity($request, 'created', 'New consultation request received from ' . $request->source);

            // Trigger notifications logic here (e.g., Resend email to admin)
            // Implementation of notification sending will be handled by a Listener or here directly if requested.

            return $request;
        });
    }

    /**
     * Update status of a consultation request.
     */
    public function updateStatus(ConsultationRequest $request, string $newStatus, ?string $userId = null): void
    {
        if ($request->status === $newStatus) {
            return;
        }

        $oldStatus = $request->status;
        $request->update(['status' => $newStatus]);

        $this->logActivity(
            $request,
            'status_change',
            "Status changed from {$oldStatus} to {$newStatus}",
            ['old_value' => $oldStatus, 'new_value' => $newStatus],
            $userId
        );
    }

    /**
     * Log an activity for a consultation request.
     */
    public function logActivity(ConsultationRequest $request, string $type, string $title, array $data = [], ?string $userId = null): ConsultationActivity
    {
        $activity = ConsultationActivity::create([
            'consultation_request_id' => $request->id,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'data' => $data,
            'activity_at' => now(),
        ]);

        $request->update([
            'last_activity_at' => now(),
            'activity_count' => $request->activity_count + 1,
        ]);

        return $activity;
    }
}
