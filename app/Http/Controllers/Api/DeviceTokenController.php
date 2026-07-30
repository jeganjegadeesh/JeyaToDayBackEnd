<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * POST /api/fcm-token  { token, platform? }
     * Registers/refreshes the FCM token for the current device. A token is
     * globally unique - if this exact device previously belonged to a
     * different account (or a different user on a shared device), the old
     * ownership row is replaced so pushes always go to the right account.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'platform' => 'nullable|in:android,ios,web',
        ]);

        DeviceToken::where('token', $data['token'])->delete();

        $deviceToken = DeviceToken::create([
            'user_id' => $request->user()->id,
            'token' => $data['token'],
            'platform' => $data['platform'] ?? null,
        ]);

        return response()->json($deviceToken, 201);
    }

    /**
     * DELETE /api/fcm-token  { token }
     * Called on logout so a signed-out device stops receiving pushes for
     * the account that was just logged out of.
     */
    public function destroy(Request $request)
    {
        $data = $request->validate(['token' => 'required|string']);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['message' => 'Device token removed.']);
    }
}
