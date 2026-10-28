<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->when($request->filled('type'), fn (Builder $q) => $q->whereRaw('LOWER(JSON_EXTRACT(data, "$.type")) = ?', [strtolower($request->input('type'))]))
            ->orderByDesc('created_at')
            ->paginate((int) ($request->input('per_page', 15)));

        $items = $notifications->getCollection()->map(function ($notification) {
            return $this->shape($notification);
        });

        return $this->paginated(__('Notifications fetched successfully.'), $notifications, fn () => collect($items));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(__('Unread notification count fetched.'), [
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(__('All notifications marked as read.'));
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        return $this->success(__('Notification marked as read.'), $this->shape($notification->fresh()));
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->findOrFail($notification)->delete();

        return $this->success(__('Notification deleted successfully.'));
    }

    /** @return array<string, mixed> */
    private function shape($notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? null,
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? null,
            'meta' => collect($data)->except(['type', 'title', 'message'])->all(),
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }
}
