<?php

namespace Modules\Website\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Website\DTOs\MessageData;
use Modules\Website\Services\WebsiteService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WebsiteController extends Controller
{
    public function __construct(
        protected WebsiteService $websiteService
    ) {}

    /**
     * Get published projects for the portfolio.
     */
    public function getPortfolio(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 9);
        $projects = $this->websiteService->getPortfolio((int) $perPage);

        return response()->json($projects);
    }

    /**
     * Get a single project by slug.
     */
    public function getProject(string $slug): JsonResponse
    {
        $projectData = $this->websiteService->getProjectBySlug($slug);

        return response()->json($projectData->toArray());
    }

    /**
     * Store a new contact message.
     */
    public function storeMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
        ]);

        $messageData = new MessageData(
            name: $validated['name'],
            email: $validated['email'],
            subject: $validated['subject'] ?? null,
            content: $validated['content'],
            ip_address: $request->ip()
        );

        $this->websiteService->submitContactForm($messageData);

        return response()->json([
            'message' => 'Message sent successfully!',
        ], 201);
    }
}
