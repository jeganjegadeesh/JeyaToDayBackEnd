<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * GET /users
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when(
                $request->role,
                fn($q) => $q->where('role', $request->role)
            )
            ->select(
                'id', 'name', 'mobile',
                'role', 'commission', 'created_at'
            )
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return response()->json(['users' => $users]);
    }

    /**
     * POST /users
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'mobile'     => 'required|string|max:15|unique:users,mobile',
            'password'   => 'required|string|min:6',
            'role'       => 'required|in:admin,retailer,user',
            'commission' => 'nullable|numeric|min:0|max:100',
        ]);

        $user = User::create([
            'name'       => $request->name,
            'mobile'     => $request->mobile,
            'password'   => Hash::make($request->password),
            'role'       => $request->role,
            'commission' => $request->commission ?? 0,
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'user'    => $user->only([
                'id', 'name', 'mobile',
                'role', 'commission'
            ]),
        ], 201);
    }

    /**
     * GET /users/{id}
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => $user->only([
                'id', 'name', 'mobile',
                'role', 'commission', 'created_at'
            ]),
        ]);
    }

    /**
     * PUT /users/{id}
     */
    public function update(
        Request $request,
        User $user
    ): JsonResponse {
        $request->validate([
            'name'       => 'required|string|max:255',
            'mobile'     => 'required|string|max:15|unique:users,mobile,' . $user->id,
            'role'       => 'required|in:admin,retailer,user',
            'commission' => 'nullable|numeric|min:0|max:100',
        ]);

        $data = [
            'name'       => $request->name,
            'mobile'     => $request->mobile,
            'role'       => $request->role,
            'commission' => $request->commission ?? 0,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'message' => 'User updated successfully.',
            'user'    => $user->fresh()->only([
                'id', 'name', 'mobile',
                'role', 'commission'
            ]),
        ]);
    }

    /**
     * DELETE /users/{id}
     */
    public function destroy(User $user): JsonResponse
    {

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.'
        ]);
    }
}