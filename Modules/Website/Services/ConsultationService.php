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
                'type' => ($data['type'] ?? 'consultation') === 'free' ? 'consultation' : ($data['type'] ?? 'consultation'),
                'project_type' => $data['project_type'] ?? null,
                'preferred_contact' => $data['preferred_contact'] ?? 'email',
                'source' => $data['source'] ?? 'website',
                'status' => 'pending',
                'last_activity_at' => now(),
            ]);

            $this->logActivity(
                $request,
                'created',
                __('website.activities.logs.created', ['source' => $request->source])
            );

            try {
                \Filament\Notifications\Notification::make()
                    ->title(__('website.activities.notifications.new_request_title'))
                    ->body(__('website.activities.notifications.new_request_body', ['name' => $request->name]))
                    ->success()
                    ->actions([
                        \Filament\Actions\Action::make('view')
                            ->button()
                            ->url(\App\Filament\Clusters\Website\Resources\ConsultationRequestResource::getUrl('view', ['record' => $request], panel: 'admin')),
                    ])
                    ->sendToDatabase(\App\Models\User::all());
            } catch (\Exception $e) {
                // Log notification failure but don't fail the request
                \Illuminate\Support\Facades\Log::error('Failed to send consultation notification: ' . $e->getMessage());
            }

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
            __('website.activities.logs.status_change', [
                'old' => __("website.consultation_requests.status_options.{$oldStatus}"),
                'new' => __("website.consultation_requests.status_options.{$newStatus}"),
            ]),
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

        $request->updateQuietly([
            'last_activity_at' => now(),
            'activity_count' => $request->activity_count + 1,
        ]);

        return $activity;
    }
}
