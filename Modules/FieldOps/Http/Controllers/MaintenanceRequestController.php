<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\FieldOps\Enums\MaintenanceRequestStatus;
use Modules\FieldOps\Http\Resources\MaintenanceRequestResource;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoMaintenanceRequest;
use Modules\FieldOps\Models\FoMaintenanceRequestMessage;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Services\FieldOpsTenantService;
use Modules\FieldOps\Services\MaintenanceRequestService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MaintenanceRequestController extends Controller
{
    public function __construct(
        private readonly FieldOpsTenantService $tenants,
        private readonly MaintenanceRequestService $requests,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = FoMaintenanceRequest::query()->with(['client', 'maintainable', 'messages.user', 'media', 'workOrders']);
        $this->tenants->scopeForUser($query, $request->user(), FoMaintenanceRequest::class);

        return response()->json([
            'success' => true,
            'data' => MaintenanceRequestResource::collection($query->latest()->paginate(50)),
        ]);
    }

    public function show(FoMaintenanceRequest $maintenanceRequest): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new MaintenanceRequestResource(
                $maintenanceRequest->load(['client', 'maintainable', 'messages.user', 'media', 'workOrders']),
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'maintainable_type' => ['required', Rule::in([Luminaire::class, ElectricalBoard::class])],
            'maintainable_id' => ['required', 'integer'],
            'category' => ['nullable', Rule::in(MaintenanceRequestService::INTAKE_CATEGORIES)],
            'impact' => ['nullable', Rule::in(MaintenanceRequestService::INTAKE_IMPACTS)],
            'description' => ['required', 'string', 'max:5000'],
            'intake_data' => ['nullable', 'array'],
        ]);
        $equipment = $data['maintainable_type']::query()->findOrFail($data['maintainable_id']);

        return response()->json([
            'success' => true,
            'data' => new MaintenanceRequestResource($this->requests->create($request->user(), $equipment, $data)),
        ], 201);
    }

    public function suggestIntake(Request $request): JsonResponse
    {
        $data = $request->validate([
            'maintainable_type' => ['required', Rule::in([Luminaire::class, ElectricalBoard::class])],
            'maintainable_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:5000'],
        ]);
        $equipment = $data['maintainable_type']::query()->findOrFail($data['maintainable_id']);

        return response()->json([
            'success' => true,
            'data' => $this->requests->suggestIntake($request->user(), $equipment, $data['description']),
        ]);
    }

    public function convert(Request $request, FoMaintenanceRequest $maintenanceRequest): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'super_admin']), 403);
        $order = $this->requests->convertToWorkOrder(
            $maintenanceRequest,
            (int) $request->user()->id,
            $request->validate([
                'assigned_employee_id' => ['nullable', 'string'],
                'priority' => ['nullable', 'string'],
                'instructions' => ['nullable', 'string'],
                'scheduled_for' => ['nullable', 'date'],
                'due_at' => ['nullable', 'date'],
            ]),
        );

        return response()->json(['success' => true, 'work_order_id' => $order->id]);
    }

    public function respond(Request $request, FoMaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_column(MaintenanceRequestStatus::cases(), 'value'))],
            'public_response' => ['nullable', 'string', 'max:5000', 'required_without_all:status,internal_notes'],
            'internal_notes' => ['nullable', 'string', 'max:5000', 'required_without_all:status,public_response'],
        ]);

        return response()->json([
            'success' => true,
            'data' => new MaintenanceRequestResource(
                $this->requests->respond($maintenanceRequest, $request->user(), $data),
            ),
        ]);
    }

    public function message(Request $request, FoMaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'visibility' => ['nullable', Rule::in([
                FoMaintenanceRequestMessage::VISIBILITY_PUBLIC,
                FoMaintenanceRequestMessage::VISIBILITY_INTERNAL,
            ])],
        ]);
        $message = $this->requests->addMessage(
            $maintenanceRequest,
            $request->user(),
            $data['body'],
            $data['visibility'] ?? FoMaintenanceRequestMessage::VISIBILITY_PUBLIC,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'visibility' => $message->visibility,
                'type' => $message->type,
                'body' => $message->body,
                'user' => $message->user ? ['id' => $message->user->id, 'name' => $message->user->name] : null,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function attachment(Request $request, FoMaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:20480'],
            'visibility' => ['nullable', Rule::in([
                FoMaintenanceRequestMessage::VISIBILITY_PUBLIC,
                FoMaintenanceRequestMessage::VISIBILITY_INTERNAL,
            ])],
            'message_id' => ['nullable', 'integer'],
        ]);
        $media = $this->requests->attach(
            $maintenanceRequest,
            $request->user(),
            $data['file'],
            $data['visibility'] ?? FoMaintenanceRequestMessage::VISIBILITY_PUBLIC,
            $data['message_id'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => MaintenanceRequestResource::attachmentPayload($media),
        ], 201);
    }

    public function showAttachment(Request $request, Media $media): BinaryFileResponse
    {
        $this->requests->assertCanViewAttachment($request->user(), $media);

        return response()->file($media->getPath());
    }

    public function confirm(Request $request, FoMaintenanceRequest $maintenanceRequest): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new MaintenanceRequestResource(
                $this->requests->confirmResolution($maintenanceRequest, $request->user()),
            ),
        ]);
    }

    public function reopen(Request $request, FoMaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:5000']]);

        return response()->json([
            'success' => true,
            'data' => new MaintenanceRequestResource(
                $this->requests->reopen($maintenanceRequest, $request->user(), $data['reason']),
            ),
        ]);
    }
}
