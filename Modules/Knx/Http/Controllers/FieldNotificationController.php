<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\FieldNotificationResource;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Services\FieldNotificationService;

/**
 * La bandeja de campo (§4.8): lo que Veld reportó y oficina todavía no ha
 * confirmado. El front la consulta cada 5 s.
 */
class FieldNotificationController extends Controller
{
    /**
     * Confirmed items are included, with `acked`, so the office can still see what
     * it just cleared after the next poll.
     */
    public function index(Request $request): array
    {
        $notifications = KnxNotification::query()
            ->with(['board', 'project', 'reportedBy'])
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->get();

        return FieldNotificationResource::list($notifications, $request);
    }

    public function ack(string $id, FieldNotificationService $notifications): Response
    {
        $notifications->ack(KnxNotification::query()->findOrFail($id));

        return response()->noContent();
    }

    public function ackAll(FieldNotificationService $notifications): Response
    {
        $notifications->ackAll();

        return response()->noContent();
    }
}
