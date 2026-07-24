<?php

declare(strict_types=1);

namespace Modules\FieldOps\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;
use Modules\FieldOps\Enums\MaintenanceRequestStatus;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoMaintenanceRequest;
use Modules\FieldOps\Models\FoMaintenanceRequestMessage;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Notifications\ClientRequestNotification;
use Modules\Intelligence\Services\GeminiService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class MaintenanceRequestService
{
    public const INTAKE_CATEGORIES = [
        'light_out',
        'flickering',
        'physical_damage',
        'electrical_issue',
        'control_issue',
        'other',
    ];

    public const INTAKE_IMPACTS = ['low', 'medium', 'high', 'emergency', 'unknown'];

    public function __construct(
        private readonly FieldOpsTenantService $tenants,
        private readonly GeminiService $gemini,
    ) {}

    public function create(User $user, Model $equipment, array $data): FoMaintenanceRequest
    {
        $clientId = $this->assertCanReportEquipment($user, $equipment);
        $position = $equipment instanceof Luminaire ? $equipment->position : null;

        $request = DB::transaction(function () use ($user, $equipment, $data, $clientId, $position): FoMaintenanceRequest {
            $request = FoMaintenanceRequest::query()->create([
                'client_id' => $clientId,
                'reported_by_user_id' => $user->id,
                'source' => 'client_portal',
                'status' => MaintenanceRequestStatus::RECEIVED,
                'category' => $data['category'] ?? null,
                'impact' => $data['impact'] ?? null,
                'description' => trim($data['description']),
                'maintainable_type' => $equipment::class,
                'maintainable_id' => $equipment->getKey(),
                'luminaire_position_id' => $position?->id,
                'installation_snapshot' => $this->snapshot($equipment),
                'intake_data' => $data['intake_data'] ?? null,
            ]);

            $request->messages()->create([
                'user_id' => $user->id,
                'visibility' => FoMaintenanceRequestMessage::VISIBILITY_PUBLIC,
                'type' => FoMaintenanceRequestMessage::TYPE_MESSAGE,
                'body' => trim($data['description']),
            ]);

            return $request;
        });

        $this->notifyBackoffice($request, 'created');

        return $request->load(['client', 'maintainable', 'messages.user', 'media']);
    }

    public function convertToWorkOrder(FoMaintenanceRequest $request, int $userId, array $planning = []): FoMaintenanceWorkOrder
    {
        return DB::transaction(function () use ($request, $userId, $planning): FoMaintenanceWorkOrder {
            $locked = FoMaintenanceRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->work_order_id) {
                return $locked->workOrder()->with(['client', 'maintainable'])->firstOrFail();
            }
            if (! in_array($locked->status, [
                MaintenanceRequestStatus::RECEIVED,
                MaintenanceRequestStatus::IN_REVIEW,
                MaintenanceRequestStatus::REOPENED,
            ], true)) {
                throw ValidationException::withMessages(['status' => 'This request cannot be converted in its current state.']);
            }

            $type = FoMaintenanceType::query()
                ->where('code', $locked->impact === 'emergency' ? FoMaintenanceType::CODE_EMERGENCY : FoMaintenanceType::CODE_CORRECTIVE)
                ->firstOrFail();
            $order = app(MaintenanceWorkOrderService::class)->create([
                'fo_maintenance_type_id' => $type->id,
                'maintainable_type' => $locked->maintainable_type,
                'maintainable_id' => $locked->maintainable_id,
                'priority' => $planning['priority'] ?? ($locked->impact === 'emergency' ? 'high' : 'normal'),
                'source' => 'client_request',
                'assigned_employee_id' => $planning['assigned_employee_id'] ?? null,
                'problem_description' => $locked->description,
                'instructions' => $planning['instructions'] ?? null,
                'scheduled_for' => $planning['scheduled_for'] ?? now(),
                'due_at' => $planning['due_at'] ?? null,
            ], $userId);

            $locked->workOrders()->syncWithoutDetaching([$order->id]);
            $locked->update([
                'work_order_id' => $order->id,
                'status' => MaintenanceRequestStatus::PLANNED,
                'acknowledged_at' => $locked->acknowledged_at ?? now(),
            ]);
            $this->appendSystemMessage($locked, 'A work order has been created for this request.');

            return $order->load(['client', 'maintainable']);
        });
    }

    public function addMessage(
        FoMaintenanceRequest $request,
        User $user,
        string $body,
        string $visibility = FoMaintenanceRequestMessage::VISIBILITY_PUBLIC,
    ): FoMaintenanceRequestMessage {
        $visibility = $this->normalizeVisibility($user, $visibility);
        $this->assertCanContribute($user, $request);

        $message = $request->messages()->create([
            'user_id' => $user->id,
            'visibility' => $visibility,
            'type' => FoMaintenanceRequestMessage::TYPE_MESSAGE,
            'body' => trim($body),
        ]);

        if ($visibility === FoMaintenanceRequestMessage::VISIBILITY_PUBLIC && ! $this->tenants->isClientUser($user)) {
            $request->update([
                'public_response' => $message->body,
                'acknowledged_at' => $request->acknowledged_at ?? now(),
            ]);
            $this->notifyReporter($request->fresh(), 'updated');
        } elseif ($this->tenants->isClientUser($user)) {
            $this->notifyBackoffice($request, 'message_received');
        }

        return $message->load('user');
    }

    public function attach(
        FoMaintenanceRequest $request,
        User $user,
        UploadedFile $file,
        string $visibility,
        ?int $messageId = null,
    ): Media {
        $visibility = $this->normalizeVisibility($user, $visibility);
        $this->assertCanContribute($user, $request);

        if ($messageId !== null && ! $request->messages()->whereKey($messageId)->exists()) {
            throw ValidationException::withMessages(['message_id' => 'The message does not belong to this request.']);
        }

        return $request
            ->addMedia($file)
            ->withCustomProperties([
                'visibility' => $visibility,
                'uploaded_by_user_id' => $user->id,
                'message_id' => $messageId,
            ])
            ->toMediaCollection('attachments');
    }

    public function respond(FoMaintenanceRequest $request, User $user, array $data): FoMaintenanceRequest
    {
        $this->assertBackoffice($user);

        return DB::transaction(function () use ($request, $user, $data): FoMaintenanceRequest {
            $locked = FoMaintenanceRequest::query()->lockForUpdate()->findOrFail($request->id);
            $nextStatus = isset($data['status']) ? MaintenanceRequestStatus::from($data['status']) : $locked->status;
            $this->assertManualTransition($locked->status, $nextStatus);

            $locked->update([
                'status' => $nextStatus,
                'acknowledged_at' => $locked->acknowledged_at ?? now(),
            ]);

            if (filled($data['public_response'] ?? null)) {
                $this->addMessage($locked, $user, $data['public_response'], FoMaintenanceRequestMessage::VISIBILITY_PUBLIC);
            }
            if (filled($data['internal_notes'] ?? null)) {
                $this->addMessage($locked, $user, $data['internal_notes'], FoMaintenanceRequestMessage::VISIBILITY_INTERNAL);
            }

            return $locked->fresh(['client', 'maintainable', 'messages.user', 'media']);
        });
    }

    public function confirmResolution(FoMaintenanceRequest $request, User $user): FoMaintenanceRequest
    {
        $this->assertCanContribute($user, $request);

        return DB::transaction(function () use ($request, $user): FoMaintenanceRequest {
            $locked = FoMaintenanceRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== MaintenanceRequestStatus::RESOLVED) {
                throw ValidationException::withMessages(['status' => 'Only a resolved request can be confirmed.']);
            }

            $locked->update([
                'status' => MaintenanceRequestStatus::CLOSED,
                'confirmed_at' => now(),
                'closed_at' => now(),
                'closed_by_user_id' => $user->id,
            ]);
            $this->appendSystemMessage($locked, 'The client confirmed that the request is resolved.', $user->id);

            return $locked->fresh(['messages.user', 'media']);
        });
    }

    public function reopen(FoMaintenanceRequest $request, User $user, string $reason): FoMaintenanceRequest
    {
        $this->assertCanContribute($user, $request);

        $reopened = DB::transaction(function () use ($request, $user, $reason): FoMaintenanceRequest {
            $locked = FoMaintenanceRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (! in_array($locked->status, [MaintenanceRequestStatus::RESOLVED, MaintenanceRequestStatus::CLOSED], true)) {
                throw ValidationException::withMessages(['status' => 'Only a resolved or closed request can be reopened.']);
            }

            $locked->update([
                'status' => MaintenanceRequestStatus::REOPENED,
                'work_order_id' => null,
                'resolved_at' => null,
                'confirmed_at' => null,
                'closed_at' => null,
                'closed_by_user_id' => null,
                'reopened_at' => now(),
                'public_response' => null,
            ]);
            $locked->messages()->create([
                'user_id' => $user->id,
                'visibility' => FoMaintenanceRequestMessage::VISIBILITY_PUBLIC,
                'type' => FoMaintenanceRequestMessage::TYPE_STATUS,
                'body' => trim($reason),
            ]);

            return $locked->fresh(['messages.user', 'media']);
        });

        $this->notifyBackoffice($reopened, 'reopened');

        return $reopened;
    }

    public function cancel(FoMaintenanceRequest $request, User $user, string $reason): FoMaintenanceRequest
    {
        $this->assertCanContribute($user, $request);
        $isClient = $this->tenants->isClientUser($user);

        $cancelled = DB::transaction(function () use ($request, $user, $reason): FoMaintenanceRequest {
            $locked = FoMaintenanceRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (! in_array($locked->status, [
                MaintenanceRequestStatus::RECEIVED,
                MaintenanceRequestStatus::IN_REVIEW,
                MaintenanceRequestStatus::REOPENED,
            ], true)) {
                throw ValidationException::withMessages(['status' => 'This request can no longer be cancelled.']);
            }

            $locked->update([
                'status' => MaintenanceRequestStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $user->id,
                'cancellation_reason' => $reason,
            ]);
            $this->appendSystemMessage($locked, $reason, $user->id);

            return $locked->fresh(['messages.user', 'media']);
        });

        if ($isClient) {
            $this->notifyBackoffice($cancelled, 'cancelled');
        } else {
            $this->notifyReporter($cancelled, 'cancelled');
        }

        return $cancelled;
    }

    public function suggestIntake(User $user, Model $equipment, string $description): array
    {
        $this->assertCanReportEquipment($user, $equipment);
        $prompt = <<<'PROMPT'
You assist a client reporting an outdoor-lighting maintenance issue. Analyze only the supplied description.
Return JSON with: category (light_out|flickering|physical_damage|electrical_issue|control_issue|other), impact (low|medium|high|emergency|unknown), summary (max 240 chars), clarification_questions (array, max 3).
Never infer a client, site, asset id, authorization, or work-order decision.
PROMPT;
        try {
            $result = $this->gemini->generateStructuredResponse($prompt."\nDescription: ".trim($description));
        } catch (Throwable $exception) {
            report($exception);
            $result = [];
        }

        $category = in_array($result['category'] ?? null, self::INTAKE_CATEGORIES, true) ? $result['category'] : 'other';
        $impact = in_array($result['impact'] ?? null, self::INTAKE_IMPACTS, true) ? $result['impact'] : 'unknown';
        $questions = collect($result['clarification_questions'] ?? [])
            ->filter(fn ($question): bool => is_string($question) && trim($question) !== '')
            ->take(3)
            ->map(fn (string $question): string => trim($question))
            ->values()
            ->all();

        return [
            'category' => $category,
            'impact' => $impact,
            'summary' => str((string) ($result['summary'] ?? $description))->squish()->limit(240)->toString(),
            'clarification_questions' => $questions,
        ];
    }

    public function markInProgress(FoMaintenanceWorkOrder $order): void
    {
        FoMaintenanceRequest::query()->where('work_order_id', $order->id)->update([
            'status' => MaintenanceRequestStatus::IN_PROGRESS,
        ]);
    }

    public function resolveFromWorkOrder(FoMaintenanceWorkOrder $order, string $response): ?FoMaintenanceRequest
    {
        $request = FoMaintenanceRequest::query()->where('work_order_id', $order->id)->lockForUpdate()->first();
        if (! $request) {
            return null;
        }

        $request->update([
            'status' => MaintenanceRequestStatus::RESOLVED,
            'resolved_at' => $order->completed_at ?? now(),
            'confirmed_at' => null,
            'closed_at' => null,
            'public_response' => $response,
        ]);
        $this->appendSystemMessage($request, $response);

        return $request->fresh('reporter');
    }

    public function rejectFromWorkOrder(FoMaintenanceWorkOrder $order, string $reason): ?FoMaintenanceRequest
    {
        $request = FoMaintenanceRequest::query()->where('work_order_id', $order->id)->lockForUpdate()->first();
        if (! $request) {
            return null;
        }

        $request->update([
            'status' => MaintenanceRequestStatus::REJECTED,
            'public_response' => $reason,
        ]);
        $this->appendSystemMessage($request, $reason);

        return $request->fresh('reporter');
    }

    public function assertCanViewAttachment(User $user, Media $media): FoMaintenanceRequest
    {
        $request = $media->model;
        if (! $request instanceof FoMaintenanceRequest || ! $this->tenants->canView($user, $request)) {
            throw new AuthorizationException;
        }
        if (
            $this->tenants->isClientUser($user)
            && $media->getCustomProperty('visibility') === FoMaintenanceRequestMessage::VISIBILITY_INTERNAL
        ) {
            throw new AuthorizationException;
        }

        return $request;
    }

    private function assertCanReportEquipment(User $user, Model $equipment): int
    {
        if (! in_array($equipment::class, [Luminaire::class, ElectricalBoard::class], true)) {
            throw new AuthorizationException;
        }

        $owners = $this->tenants->ownerClientIds($equipment);
        if ($owners->count() !== 1) {
            throw new AuthorizationException;
        }
        $clientId = (int) $owners->first();
        $canReport = $this->tenants->isClientUser($user)
            && $user->fieldOpsClients()
                ->where('fo_clients.id', $clientId)
                ->wherePivot('can_view', true)
                ->wherePivot('can_report', true)
                ->wherePivot('is_active', true)
                ->exists();
        if (! $canReport) {
            throw new AuthorizationException;
        }

        return $clientId;
    }

    private function assertCanContribute(User $user, FoMaintenanceRequest $request): void
    {
        if (! $this->tenants->isClientUser($user)) {
            $this->assertBackoffice($user);

            return;
        }

        $canReport = $this->tenants->canView($user, $request)
            && $user->fieldOpsClients()
                ->where('fo_clients.id', $request->client_id)
                ->wherePivot('can_report', true)
                ->wherePivot('is_active', true)
                ->exists();
        if (! $canReport) {
            throw new AuthorizationException;
        }
    }

    private function assertBackoffice(User $user): void
    {
        if (! $user->hasAnyRole(['admin', 'super_admin'])) {
            throw new AuthorizationException;
        }
    }

    private function normalizeVisibility(User $user, string $visibility): string
    {
        if ($this->tenants->isClientUser($user)) {
            return FoMaintenanceRequestMessage::VISIBILITY_PUBLIC;
        }
        if (! in_array($visibility, [FoMaintenanceRequestMessage::VISIBILITY_PUBLIC, FoMaintenanceRequestMessage::VISIBILITY_INTERNAL], true)) {
            throw ValidationException::withMessages(['visibility' => 'Invalid message visibility.']);
        }

        return $visibility;
    }

    private function assertManualTransition(MaintenanceRequestStatus $current, MaintenanceRequestStatus $next): void
    {
        if ($current === $next) {
            return;
        }

        $allowed = match ($current) {
            MaintenanceRequestStatus::RECEIVED => [MaintenanceRequestStatus::IN_REVIEW, MaintenanceRequestStatus::REJECTED, MaintenanceRequestStatus::DUPLICATE],
            MaintenanceRequestStatus::IN_REVIEW, MaintenanceRequestStatus::REOPENED => [MaintenanceRequestStatus::REJECTED, MaintenanceRequestStatus::DUPLICATE],
            default => [],
        };
        if (! in_array($next, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Invalid maintenance request transition.']);
        }
    }

    private function appendSystemMessage(FoMaintenanceRequest $request, string $body, ?int $userId = null): void
    {
        $request->messages()->create([
            'user_id' => $userId,
            'visibility' => FoMaintenanceRequestMessage::VISIBILITY_PUBLIC,
            'type' => FoMaintenanceRequestMessage::TYPE_STATUS,
            'body' => trim($body),
        ]);
    }

    private function snapshot(Model $equipment): array
    {
        if ($equipment instanceof Luminaire) {
            return [
                'kind' => 'luminaire',
                'serial_number' => $equipment->serial_number,
                'luminaire_frame_id' => $equipment->luminaire_frame_id,
                'luminaire_position_id' => $equipment->luminaire_position_id,
                'frame_position' => $equipment->frame_position,
                'frame_x' => $equipment->frame_x,
                'frame_y' => $equipment->frame_y,
                'scale_x' => $equipment->scale_x,
                'scale_y' => $equipment->scale_y,
                'position_version' => $equipment->position_version,
                'position_source' => $equipment->position_source,
                'luminaire_type_id' => $equipment->luminaire_type_id,
                'captured_at' => now()->toIso8601String(),
            ];
        }

        /** @var ElectricalBoard $equipment */
        return [
            'kind' => 'electrical_board',
            'electrical_board_type_id' => $equipment->electrical_board_type_id,
            'location_description' => $equipment->getTranslation('location_description', app()->getLocale(), false),
            'lat' => $equipment->lat,
            'lng' => $equipment->lng,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    private function notifyReporter(FoMaintenanceRequest $request, string $event): void
    {
        $request->loadMissing('reporter');
        if ($request->reporter) {
            $request->reporter->notify(new ClientRequestNotification($request, $event));
        }
    }

    private function notifyBackoffice(FoMaintenanceRequest $request, string $event): void
    {
        User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'super_admin']))
            ->each(fn (User $user) => $user->notify(new ClientRequestNotification($request, $event)));
    }
}
