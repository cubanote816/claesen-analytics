<?php

namespace Modules\Website\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Prospects\Services\LeadService;
use Modules\Website\Services\ConsultationService;

class ContactController extends Controller
{
    protected $leadService;
    protected $consultationService;

    public function __construct(LeadService $leadService, ConsultationService $consultationService)
    {
        $this->leadService = $leadService;
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

            // Add metadata
            $metadata = [
                'source' => $request->header('Referer') ?? 'website_contact',
                'ip' => $request->ip(),
            ];

            $data = array_merge($validated, $metadata);

            // Persist lead in Prospects
            $prospect = $this->leadService->persistContactLead($data);

            // Create a consultation request in Website to persist the actual message, trigger notifications and emails
            // The ConsultationService expects 'name', 'email', 'message' and optionally 'source', 'type'
            $this->consultationService->createRequest([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'message' => $validated['message'],
                'type' => 'consultation',
                'source' => $metadata['source'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mensaje enviado correctamente' // Matching the Sport API expected response
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error processing contact request: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al procesar la solicitud de contacto.'
            ], 500);
        }
    }
}
