<?php

namespace Modules\Website\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Website\Services\ConsultationService;

class ConsultationController extends Controller
{
    protected $consultationService;

    public function __construct(ConsultationService $consultationService)
    {
        $this->consultationService = $consultationService;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|string|in:consultation,free,quote,project',
            'project_type' => 'nullable|string|max:255',
            'preferred_contact' => 'nullable|string|in:email,phone',
        ]);

        // F4/CLA-478: `source` is never accepted from the client — see
        // Modules\Website\Services\ConsultationService::handlePublicIntake()/
        // LeadSourceNormalizer, which derive it server-side.
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
