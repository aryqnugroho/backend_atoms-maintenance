<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * List the authenticated user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = (int) $request->input('per_page', 20);
        $perPage = min($perPage, 50);

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Transform to match frontend Notification type
        $transformed = $notifications->through(function ($notification) {
            $data = $notification->data;
            return [
                'id' => $notification->id,
                'type' => $data['type'] ?? $notification->type,
                'title' => $data['title'] ?? '',
                'message' => $data['message'] ?? '',
                'is_read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toISOString(),
                'data' => $data,
            ];
        });

        return $this->success($transformed, 'Notifications retrieved successfully');
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(string $id): JsonResponse
    {
        $user = Auth::user();
        $notification = $user->notifications()->find($id);

        if (!$notification) {
            return $this->error('Notification not found.', null, 404);
        }

        $notification->markAsRead();

        return $this->success(null, 'Notification marked as read');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return $this->success(null, 'All notifications marked as read');
    }
}
