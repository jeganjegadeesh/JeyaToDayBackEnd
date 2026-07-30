<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordResetRequestController extends Controller
{
    /** GET /api/password-reset-requests?status=pending - Admin only */
    public function index(Request $request)
    {
        $query = PasswordResetRequest::with('user:id,name,phone_number,type')
            ->where('company_id', $request->user()->company_id)
            ->latest('requested_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * POST /api/password-reset-requests/{passwordResetRequest}/resolve - Admin only
     * Resets the requesting user's password back to the system default and
     * marks the request resolved.
     */
    public function resolve(Request $request, PasswordResetRequest $passwordResetRequest)
    {
        if ($passwordResetRequest->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($passwordResetRequest->status === 'resolved') {
            return response()->json(['message' => 'This request was already resolved.'], 422);
        }

        $user = $passwordResetRequest->user;
        if (! $user) {
            return response()->json(['message' => 'The associated user no longer exists.'], 422);
        }

        $user->update([
            'password' => Hash::make(config('app.default_user_password', '123456')),
            'updated_by' => $request->user()->id,
        ]);

        $passwordResetRequest->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Password reset to default. The user can now log in with the default password.',
            'request' => $passwordResetRequest->fresh('user'),
        ]);
    }
}
