<?php

namespace Modules\Website\Services;

use Modules\Core\Models\Site;
use Modules\Core\Services\OrganizationContext;
use Modules\Prospects\Services\LeadService;
use Modules\Website\Jobs\SendConsultationEmailJob;
use Modules\Website\Models\ConsultationEmailDelivery;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\ConsultationActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                'status' => ConsultationRequest::STATUS_NEW,
                'last_activity_at' => now(),
                'custom_fields' => $utm !== [] ? ['utm' => $utm] : null,
                // F4/CLA-475: only populated when the caller actually sent a
                // truthy `consent` — see ConsultationRequest's own docblock
                // for why this is never made mandatory here.
                'consent_given_at' => ! empty($data['consent']) ? now() : null,
                'consent_policy_version' => ! empty($data['consent']) ? ($data['policy_version'] ?? null) : null,
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

            // F4/CLA-473: both e-mails this request can trigger (internal
            // notice, client confirmation) are recorded as their own
            // ConsultationEmailDelivery row inside this same transaction —
            // so they roll back together with $request if anything above
            // fails — and only *dispatched* after commit
            // (Modules\Website\Jobs\SendConsultationEmailJob does the
            // actual send; see its own docblock for why $tries=1 and no
            // built-in backoff). Recipient still comes from the site
            // (falling back to the single global config CLA-532
            // introduced) — empty means "skip the internal notice, log a
            // warning", the same persist-but-skip behaviour CLA-532
            // established, never a hard failure. The confirmation to the
            // client has no such gate: $consultation->email is always
            // present (a required field).
            $site = Site::query()->find($request->site_id);
            $deliveryIds = [];

            $internalRecipient = $site?->notificationEmail();

            if (empty($internalRecipient)) {
                Log::warning('ConsultationService: no notification recipient configured for this site — internal notice skipped.', [
                    'consultation_request_id' => $request->id,
                    'site_id' => $request->site_id,
                ]);
            } else {
                $deliveryIds[] = ConsultationEmailDelivery::create([
                    'site_id' => $request->site_id,
                    'consultation_request_id' => $request->id,
                    'type' => ConsultationEmailDelivery::TYPE_INTERNAL,
                    'recipient' => $internalRecipient,
                ])->id;
            }

            $deliveryIds[] = ConsultationEmailDelivery::create([
                'site_id' => $request->site_id,
                'consultation_request_id' => $request->id,
                'type' => ConsultationEmailDelivery::TYPE_CONFIRMATION,
                'recipient' => $request->email,
            ])->id;

            DB::afterCommit(function () use ($deliveryIds): void {
                foreach ($deliveryIds as $deliveryId) {
                    SendConsultationEmailJob::dispatch($deliveryId);
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
        $request->maybeStampFirstResponse($oldStatus, $newStatus);
        $request->updateQuietly([
            'status' => $newStatus,
            'first_response_at' => $request->first_response_at,
        ]);

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
