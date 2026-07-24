<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /** GET /api/users?type=&search= */
    public function index(Request $request)
    {
        $query = User::query()->where('type', '!=', 'retailer');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        return response()->json($query->with('company')->latest()->paginate(20));
    }

    /** POST /api/users */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:users,phone_number',
            'type' => 'required|in:admin,manager',
            'profile_image' => 'nullable|image|max:2048', // not required
        ]);

        $data['password'] = Hash::make(config('app.default_user_password', '123456'));
        $data['company_id'] = $request->user()->company_id;
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image')->store('userAssets', 'public');
        }

        $user = User::create($data);
        return response()->json($user, 201);
    }

    /** GET /api/users/{user} */
    public function show(User $user)
    {
        return response()->json($user->load('company'));
    }

    /** PUT /api/users/{user} */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|unique:users,phone_number,'.$user->id,
            'type' => 'sometimes|in:admin,manager',
            'profile_image' => 'nullable|image|max:2048',
        ]);

        $data['updated_by'] = $request->user()->id;

        if ($request->hasFile('profile_image')) {
            // remove old image if one exists
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $data['profile_image'] = $request->file('profile_image')->store('userAssets', 'public');
        }

        $user->update($data);
        return response()->json($user);
    }

    /** DELETE /api/users/{user} - soft delete flag, Admin only (enforced by route middleware) */
   public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        $user->softDeleteFlag();
        return response()->json(['message' => 'User deleted.']);
    }

    /** POST /api/users/{user}/restore - Admin only */
    public function restore(User $user)
    {
        $user->restoreFlag();

        return response()->json(['message' => 'User restored.']);
    }

    /** POST /api/users/{user}/reset-password - Admin only, resets to default 123456 */
    public function resetPassword(Request $request, User $user)
    {
        $user->update([
            'password' => Hash::make(config('app.default_user_password', '123456')),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Password reset to default.']);
    }

}
