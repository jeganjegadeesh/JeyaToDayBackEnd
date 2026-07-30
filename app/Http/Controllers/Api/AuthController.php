<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /** POST /api/login  { phone_number, password } */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = User::withoutGlobalScopes()
            ->where('phone_number', $request->phone_number)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid phone number or password.'], 401);
        }

        $token = $user->createToken('aj-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('company'),
        ]);
    }

    /**
     * POST /api/forgot-password  { phone_number }
     *
     * Public (unauthenticated) endpoint. Records a request that the
     * company's Admin(s) can see and act on (see PasswordResetRequestController).
     * Limited to one request per phone number per 24 hours.
     */
    public function forgotPassword(Request $request, NotificationService $notifications)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = User::withoutGlobalScopes()
            ->where('phone_number', $request->phone_number)
            ->first();

        if (! $user) {
            return response()->json(['message' => 'No account found with this phone number.'], 404);
        }

        $alreadyRequestedToday = PasswordResetRequest::where('phone_number', $user->phone_number)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($alreadyRequestedToday) {
            return response()->json([
                'message' => 'A password reset request was already sent today. Please try again after 24 hours.',
            ], 429);
        }

        $resetRequest = PasswordResetRequest::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'phone_number' => $user->phone_number,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $notifications->passwordResetRequested($resetRequest);

        return response()->json([
            'message' => 'Your request has been sent to the admin. You will be able to log in once your password is reset.',
        ]);
    }

    /** POST /api/logout */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /** GET /api/me */
    public function me(Request $request)
    {
        return response()->json($request->user()->load('company'));
    }

    /** PUT /api/profile  { name, phone_number } */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|unique:users,phone_number,'.$user->id,
            'theme' => 'sometimes|in:light,dark',
            'language' => 'sometimes|in:ta,en',
            'font_size' => 'sometimes|in:S,M,L,XL',
            'profile_image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $data['profile_image'] = $request->file('profile_image')->store('userAssets', 'public');
        }

        $user->update($data);

        return response()->json($user);
    }

    /** PUT /api/profile/password  { current_password, new_password } */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:4|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'Password updated successfully.']);
    }
}