<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Events\NotificationCreated;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    /**
     * Show notifications page (Inertia)
     */
    public function showPage(Request $request)
    {
        return Inertia::render('Notifications/Index');
    }

    /**
     * Get notifications for current user (API/JSON)
     */
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'data' => $notification->data,
                    'read' => $notification->read,
                    'created_at' => $notification->created_at->toIso8601String(),
                    'time' => $notification->created_at->diffForHumans(),
                ];
            });

        $unreadCount = Notification::where('user_id', $request->user()->id)
            ->where('read', false)
            ->count();

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->where('read', false)
            ->update([
                'read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    /**
     * Delete a notification
     */
    public function destroy(Request $request, $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Helper: Create and broadcast notification
     */
    public static function createAndBroadcast(int $userId, string $type, string $title, string $message, ?array $data = null): Notification
    {
        $notification = Notification::createForUser($userId, $type, $title, $message, $data);

        event(new NotificationCreated($notification));

        return $notification;
    }

    /**
     * API: Get notifications for current user
     */
    public function apiIndex(Request $request)
    {
        $limit = $request->get('limit', 50);

        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'data' => $notification->data,
                    'read' => $notification->read,
                    'created_at' => $notification->created_at->toIso8601String(),
                    'time' => $notification->created_at->diffForHumans(),
                ];
            });

        $unreadCount = Notification::where('user_id', $request->user()->id)
            ->where('read', false)
            ->count();

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * API: Mark notification as read
     */
    public function apiMarkAsRead(Request $request, $notification)
    {
        $notif = Notification::where('user_id', $request->user()->id)
            ->findOrFail($notification);

        $notif->update([
            'read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * API: Mark all notifications as read
     */
    public function apiMarkAllAsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->where('read', false)
            ->update([
                'read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    /**
     * API: Delete a notification
     */
    public function apiDestroy(Request $request, $notification)
    {
        $notif = Notification::where('user_id', $request->user()->id)
            ->findOrFail($notification);

        $notif->delete();

        return response()->json(['success' => true]);
    }
}
