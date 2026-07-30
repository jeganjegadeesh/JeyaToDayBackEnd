<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotificationRecipient;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** GET /api/notifications - Admin only. Newest first, includes read state. */
    public function index(Request $request)
    {
        $recipients = AppNotificationRecipient::with('notification')
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(20);

        $recipients->getCollection()->transform(fn (AppNotificationRecipient $r) => $this->format($r));

        return response()->json($recipients);
    }

    /** GET /api/notifications/unread-count - Admin only. Drives the app-icon badge. */
    public function unreadCount(Request $request)
    {
        $count = AppNotificationRecipient::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    /** POST /api/notifications/{notification}/read - Admin only. */
    public function markRead(Request $request, AppNotificationRecipient $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $notification->is_read) {
            $notification->update(['is_read' => true, 'read_at' => now()]);
        }

        $unread = AppNotificationRecipient::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['message' => 'Marked as read.', 'unread_count' => $unread]);
    }

    /** POST /api/notifications/read-all - Admin only. */
    public function markAllRead(Request $request)
    {
        AppNotificationRecipient::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.', 'unread_count' => 0]);
    }

    /**
     * POST /api/notifications/test - Admin only.
     * Sends a real push (through FcmService) to every device token
     * registered to the current admin, and creates a normal notification
     * record so it shows up in the feed / unread count / badge too. Handy
     * for confirming the FCM + device-token + badge wiring end-to-end
     * without needing to trigger a real forgot-password or bill flow.
     */
    public function test(Request $request)
    {
        $notifications = app(\App\Services\NotificationService::class);

        $notification = $notifications->notifyAdmins(
            companyId: $request->user()->company_id,
            type: 'test',
            title: 'Test notification',
            body: 'If you can see this, push notifications are working.',
            data: ['screen' => 'notifications'],
        );

        if (! $notification) {
            return response()->json(['message' => 'No admins found for your company - nothing to send.'], 422);
        }

        $tokenCount = \App\Models\DeviceToken::where('user_id', $request->user()->id)->count();

        return response()->json([
            'message' => $tokenCount > 0
                ? "Test notification queued to {$tokenCount} device(s) for your account."
                : 'Notification created, but you have no registered device tokens yet - open the app once (logged in) so it can register one, then try again.',
        ]);
    }

    protected function format(AppNotificationRecipient $r): array
    {
        $n = $r->notification;

        return [
            'id' => $r->id,
            'type' => $n?->type,
            'title' => $n?->title,
            'body' => $n?->body,
            'data' => $n?->data,
            'reference_id' => $n?->reference_id,
            'reference_type' => $n?->reference_type,
            'is_read' => $r->is_read,
            'read_at' => $r->read_at,
            'created_at' => $n?->created_at,
        ];
    }
}
