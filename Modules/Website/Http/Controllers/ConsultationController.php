<?php

namespace Modules\Website\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Website\Services\ConsultationService;
use Illuminate\Validation\ValidationException;

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
            'source' => 'nullable|string|max:255',
        ]);

        try {
            $consultation = $this->consultationService->createRequest($validated);

            return response()->json([
                'message' => 'Consultation request created successfully.',
                'data' => $consultation
            ], 201);
        } catch (\Exception $e) {
            // Log error
            return response()->json([
                'message' => 'Failed to create consultation request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
