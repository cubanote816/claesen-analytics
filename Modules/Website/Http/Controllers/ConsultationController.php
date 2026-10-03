<?php

namespace Modules\Website\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Core\Services\OrganizationContext;
use Modules\Website\Models\WebsiteIntakeSpamAttempt;
use Modules\Website\Services\ConsultationService;
use Modules\Website\Services\IntakeSpamGuard;

class ConsultationController extends Controller
{
    protected $consultationService;

    public function __construct(ConsultationService $consultationService)
    {
        $this->consultationService = $consultationService;
    }

    public function store(Request $request)
    {
        $honeypotField = config('website.intake_hardening.honeypot_field');

        // CLA-484: the Electro Bertels form sends fields the intake used to
        // discard. All optional so the current Claesen frontend's request
        // shape keeps working unchanged — except `consent_version`, which is
        // REQUIRED only for sites that require it (config-declared site-key
        // list, resolved from this request's site — approver decision 1A,
        // never a hardcoded id check).
        $siteKey = app(OrganizationContext::class)->site()?->key;
        $consentVersionRequired = in_array(
            $siteKey,
            (array) config('website.intake_hardening.consent_version_required_site_keys', ['electro-bertels']),
            true
        );

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'message' => 'required|string|max:'.config('website.intake_hardening.message_max_length', 5000),
            'type' => 'nullable|string|in:consultation,free,quote,project',
            'project_type' => 'nullable|string|max:255',
            'preferred_contact' => 'nullable|string|in:email,phone',
            // CLA-484: Bertels form fields — segment mirrors the form's
            // radiogroup, postal_code/subject are informational, locale is
            // the language the visitor wrote in (reply language).
            'segment' => 'nullable|string|in:particulier,bedrijf,industrie',
            'postal_code' => 'nullable|string|max:16',
            'subject' => 'nullable|string|max:255',
            'locale' => 'nullable|string|in:nl,fr,en,de',
            'consent_version' => ($consentVersionRequired ? 'required' : 'nullable').'|string|max:50',
            // F4/CLA-475: none of these three are advertised in
            // docs/api/website-v1-openapi.yaml (the honeypot field
            // deliberately, Turnstile/consent because the separate Astro
            // frontend doesn't send them yet) — all optional, so the
            // current frontend's request shape keeps working unchanged.
            $honeypotField => 'nullable|string|max:255',
            'turnstile_token' => 'nullable|string',
            'consent' => 'nullable|boolean',
            'policy_version' => 'nullable|string|max:50',
        ]);

        // F4/CLA-478: `source` is never accepted from the client — see
        // Modules\Website\Services\ConsultationService::handlePublicIntake()/
        // LeadSourceNormalizer, which derive it server-side.
        $guard = app(IntakeSpamGuard::class);
        $siteId = app(OrganizationContext::class)->siteId();

        // F4/CLA-475: a real visitor never fills this field in — silently
        // drop (fake success, no record persisted) rather than a 422,
        // which would just teach a scraping bot which field to leave
        // blank next time.
        if ($guard->isHoneypotTriggered($validated)) {
            $guard->recordAttempt($siteId, $request->ip(), WebsiteIntakeSpamAttempt::REASON_HONEYPOT);

            return response()->json([
                'message' => 'Consultation request created successfully.',
            ], 201);
        }

        // Throws a ValidationException (=> Laravel's normal automatic 422
        // JSON render, same as the $request->validate() call above) only
        // when Turnstile is both configured AND enforced — a no-op today.
        $guard->assertTurnstilePasses($request->input('turnstile_token'), $siteId, $request->ip());

        try {
            $response = $this->consultationService->handlePublicIntake($request, $validated);

            return response()->json($response, 201);
        } catch (\Throwable $e) {
            // F4/CLA-478: never $e->getMessage() in a public response — the
            // real error is logged, the caller only ever sees a generic
            // message. The previous version of this catch block returned
            // the raw exception message directly, a real internal-detail
            // leak this closes.
            Log::error('Failed to create consultation request.', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to create consultation request.',
            ], 500);
        }
    }
}
