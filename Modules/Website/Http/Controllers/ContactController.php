<?php

namespace Modules\Website\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Website\Services\ConsultationService;

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
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'message' => 'required|string',
            ]);

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
