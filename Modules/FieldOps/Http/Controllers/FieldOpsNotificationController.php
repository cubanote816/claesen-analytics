<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FieldOpsNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $this->query($request)
            ->latest('created_at')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->query($request)->whereNull('read_at')->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $record = $this->query($request)->findOrFail($notification);
        $record->markAsRead();

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->query($request)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'FieldOps notifications marked as read']);
    }

    private function query(Request $request)
    {
        return $request->user()
            ->notifications()
            ->where('data->viewData->module', 'fieldops');
    }
}
