<?php

declare(strict_types=1);

namespace Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Modules\Analytics\Enums\AppSource;
use Modules\Analytics\Enums\EventName;
use Modules\Analytics\Http\Requests\StoreAppEventRequest;
use Modules\Analytics\Services\EventTracker;

class EventController extends Controller
{
    public function __construct(private readonly EventTracker $tracker) {}

    public function store(StoreAppEventRequest $request): JsonResponse
    {
        // No auth:sanctum middleware on this route on purpose — the global
        // statefulApi() middleware (bootstrap/app.php) already resolves the
        // user from the session/token when one is present, and leaves it
        // null otherwise. That null case is a legitimate anonymous event,
        // not an error.
        $this->tracker->track(
            eventName: EventName::from($request->validated('event_name')),
            app: AppSource::from($request->validated('app')),
            sessionId: $request->validated('session_id'),
            user: $request->user(),
            entityType: $request->validated('entity_type'),
            entityId: $request->validated('entity_id'),
            properties: $request->validated('properties') ?? [],
            durationMs: $request->validated('duration_ms'),
            occurredAt: $request->filled('occurred_at') ? Carbon::parse($request->validated('occurred_at')) : null,
        );

        return response()->json(['status' => 'accepted'], 202);
    }
}
