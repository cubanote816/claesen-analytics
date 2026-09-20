<?php

namespace Modules\Website\Services;

use Modules\Core\Models\Site;
use Modules\Core\Services\OrganizationContext;
use Modules\Prospects\Services\LeadService;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\ConsultationActivity;
use Modules\Website\Mail\NewConsultationRequestMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class ConsultationService
{
    private const IDEMPOTENCY_TTL_MINUTES = 10;

    /**
     * F4/CLA-478 of the multi-organization program — docs/ai/adr-multi-organization.md.
     *
     * The single canonical entry point both public intake controllers
     * (ConsultationController, ContactController) call — see their own
     * docblocks for the duplication/inconsistency this replaces (only one
     * of the two used to create a Prospects lead; the other exposed raw
     * exception messages; both trusted a client-submitted `source` value).
     *
     * Idempotency: an optional Idempotency-Key request header is honoured
     * — a retried request with the same key returns the exact same
     * response (array form, never a live model — caching a raw Eloquent
     * object here would hit the same Cache::remember()/database-store
     * unserialize() corruption already fixed in CLA-471) instead of
     * creating a second lead. No header present => no idempotency check,
     * which is the safe default for the current frontend (doesn't send
     * one) and never regresses existing behaviour.
     *
     * @return array{message: string, data: array}
     */
    public function handlePublicIntake(Request $request, array $validated): array
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        $cacheKey = $idempotencyKey ? 'website.consultation-intake.'.sha1($idempotencyKey) : null;

        if ($cacheKey && ($cached = Cache::get($cacheKey)) !== null) {
            return $cached;
        }

        $normalizer = app(LeadSourceNormalizer::class);
        $siteId = app(OrganizationContext::class)->siteId() ?? Site::claesenId();
        $source = $normalizer->resolveSource($request);
        $utm = $normalizer->resolveUtm($request);

        $consultation = $this->createRequest(
            array_merge($validated, ['site_id' => $siteId, 'source' => $source]),
            $utm
        );

        // ADR D11: Prospects/audiences stay 100% Claesen — a Bertels
        // consultation is a real lead for Bertels, but never feeds the
        // Claesen-only Prospects/Mailing domain.
        if ($siteId === Site::claesenId()) {
            try {
                app(LeadService::class)->persistContactLead([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'message' => $validated['message'],
                    'source' => $source,
                    'ip' => $request->ip(),
                ]);
            } catch (\Throwable $e) {
                // A Prospects-side failure must never take down the
                // consultation itself — it's already persisted above.
                Log::error('ConsultationService: failed to persist the Prospects lead.', [
                    'consultation_request_id' => $consultation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $response = [
            'message' => 'Consultation request created successfully.',
            'data' => $consultation->toArray(),
        ];

        if ($cacheKey) {
            Cache::put($cacheKey, $response, now()->addMinutes(self::IDEMPOTENCY_TTL_MINUTES));
        }

        return $response;
    }

    /**
     * Create a new consultation request from public form.
     *
     * @param array $utm Optional, normalized (LeadSourceNormalizer) —
     *                    stored under custom_fields['utm'], this table's
     *                    own established extensibility point (no
     *                    dedicated utm_* columns exist).
     */
    public function createRequest(array $data, array $utm = []): ConsultationRequest
    {
        return DB::transaction(function () use ($data, $utm) {
            $request = ConsultationRequest::create([
                // F4/CLA-478: derived from the resolved site (ResolveRequestSite
                // middleware / OrganizationContext), falling back to Claesen for
                // any caller that doesn't pass one — e.g. the existing direct
                // createRequest() callers in Modules/Website/tests, and any
                // future non-HTTP caller with no request context at all.
                'site_id' => $data['site_id'] ?? Site::claesenId(),
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
                'custom_fields' => $utm !== [] ? ['utm' => $utm] : null,
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
                    ->sendToDatabase(\Modules\Core\Models\User::all());
            } catch (\Exception $e) {
                // Log notification failure but don't fail the request
                \Illuminate\Support\Facades\Log::error('Failed to send consultation notification: ' . $e->getMessage());
            }

            // Schedule email after transaction commits — avoids sending on rollback
            // and keeps the DB lock free from external HTTP calls.
            // CLA-532: recipient from config (was hardcoded); if unset, skip the
            // notification and log a warning — the consultation is already
            // persisted and the endpoint still returns 201. A misconfigured
            // Graph mailer now raises MailConfigurationException (a
            // RuntimeException), which the catch below handles — it no longer
            // throws a TypeError that would escape and 500 after the commit.
            DB::afterCommit(function () use ($request) {
                $to = config('website.consultation_notification_email');

                if (empty($to)) {
                    Log::warning('ConsultationService: website.consultation_notification_email is not configured — new-request notification skipped.', [
                        'consultation_request_id' => $request->id,
                    ]);

                    return;
                }

                try {
                    Mail::mailer('microsoft-graph')
                        ->to($to)
                        ->send(new NewConsultationRequestMail($request));
                } catch (\Exception $e) {
                    Log::error('Failed to send consultation email: ' . $e->getMessage());
                }
            });

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
        $request->updateQuietly(['status' => $newStatus]);

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
