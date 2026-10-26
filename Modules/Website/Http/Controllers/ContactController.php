<?php

namespace Modules\Website\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Core\Services\OrganizationContext;
use Modules\Website\Models\WebsiteIntakeSpamAttempt;
use Modules\Website\Services\ConsultationService;
use Modules\Website\Services\IntakeSpamGuard;

/**
 * F4/CLA-478 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * A thin, backward-compatible adapter over the same simpler request shape
 * this endpoint has always accepted (name/email/message only) — the actual
 * intake logic (site derivation, source/UTM normalization, idempotency,
 * the Prospects lead) all live in one place now:
 * ConsultationService::handlePublicIntake(). Before this, this controller
 * called both LeadService and ConsultationService::createRequest()
 * directly and independently from /consultations — the two endpoints
 * behaved inconsistently (only this one ever created a Prospects lead)
 * and each duplicated its own source-handling.
 */
class ContactController extends Controller
{
    protected $consultationService;

    public function __construct(ConsultationService $consultationService)
    {
        $this->consultationService = $consultationService;
    }

    public function store(Request $request)
    {
        $honeypotField = config('website.intake_hardening.honeypot_field');

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'message' => 'required|string|max:'.config('website.intake_hardening.message_max_length', 5000),
                // F4/CLA-475: see ConsultationController's own docblock for
                // why these three are all optional and undocumented.
                $honeypotField => 'nullable|string|max:255',
                'turnstile_token' => 'nullable|string',
                'consent' => 'nullable|boolean',
                'policy_version' => 'nullable|string|max:50',
            ]);

            $guard = app(IntakeSpamGuard::class);
            $siteId = app(OrganizationContext::class)->siteId();

            if ($guard->isHoneypotTriggered($validated)) {
                $guard->recordAttempt($siteId, $request->ip(), WebsiteIntakeSpamAttempt::REASON_HONEYPOT);

                return response()->json([
                    'success' => true,
                    'message' => 'Mensaje enviado correctamente',
                ], 201);
            }

            $guard->assertTurnstilePasses($request->input('turnstile_token'), $siteId, $request->ip());

            $this->consultationService->handlePublicIntake($request, array_merge($validated, [
                'type' => 'consultation',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Mensaje enviado correctamente', // Matching the Sport API expected response
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error processing contact request.', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al procesar la solicitud de contacto.',
            ], 500);
        }
    }
}
