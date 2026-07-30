<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AppNotificationRecipient;
use App\Models\Bill;
use App\Models\DeviceToken;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Notifications are for Admin users only (see spec). Every notification is
 * created once (app_notifications) and fanned out to every admin of the
 * relevant company (app_notification_recipients) so each admin has their
 * own read/unread state and app-icon badge count.
 */
class NotificationService
{
    public function __construct(protected FcmService $fcm) {}

    /**
     * Create + push a notification to every admin belonging to $companyId.
     */
    public function notifyAdmins(
        ?int $companyId,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?int $referenceId = null,
        ?string $referenceType = null
    ): ?AppNotification {
        if (! $companyId) {
            return null;
        }

        $admins = User::where('company_id', $companyId)
            ->where('type', 'admin')
            ->where('is_deleted', 0)
            ->get(['id']);

        if ($admins->isEmpty()) {
            return null;
        }

        $notification = DB::transaction(function () use ($companyId, $type, $title, $body, $data, $referenceId, $referenceType, $admins) {
            $notification = AppNotification::create([
                'company_id' => $companyId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
            ]);

            $rows = $admins->map(fn ($admin) => [
                'app_notification_id' => $notification->id,
                'user_id' => $admin->id,
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            AppNotificationRecipient::insert($rows);

            return $notification;
        });

        // Push to each admin's registered devices, badge = that admin's own unread count.
        foreach ($admins as $admin) {
            $tokens = DeviceToken::where('user_id', $admin->id)->pluck('token')->all();
            if (empty($tokens)) {
                continue;
            }

            $unreadCount = AppNotificationRecipient::where('user_id', $admin->id)
                ->where('is_read', false)
                ->count();

            $this->fcm->sendToTokens(
                $tokens,
                $title,
                $body,
                array_merge($data, [
                    'type' => $type,
                    'notification_id' => (string) $notification->id,
                ]),
                $unreadCount
            );
        }

        return $notification;
    }

    /** Fired from AuthController::forgotPassword() once a request is recorded. */
    public function passwordResetRequested(PasswordResetRequest $request): void
    {
        $request->loadMissing('user');
        $name = $request->user->name ?? $request->phone_number;

        $this->notifyAdmins(
            companyId: $request->company_id,
            type: 'password_reset_request',
            title: 'Password reset request',
            body: "{$name} ({$request->phone_number}) requested a password reset.",
            data: [
                'screen' => 'password_reset_requests',
                'request_id' => (string) $request->id,
            ],
            referenceId: $request->id,
            referenceType: 'password_reset_request',
        );
    }

    /** Fired from BillController::generate() right after a bill is persisted. */
    public function newBillGenerated(Bill $bill): void
    {
        $bill->loadMissing('retailer');
        $retailerName = $bill->retailer->name ?? 'a retailer';

        $this->notifyAdmins(
            companyId: $bill->company_id,
            type: 'new_bill',
            title: 'New bill generated',
            body: "A new bill of Rs. {$bill->grand_total} was generated for {$retailerName}.",
            data: [
                'screen' => 'bill_detail',
                'bill_id' => (string) $bill->id,
            ],
            referenceId: $bill->id,
            referenceType: 'bill',
        );
    }
}
