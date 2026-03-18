<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function login(string $mobile, string $password): array|null
    {
        $user = User::where('mobile', $mobile)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        // Revoke old tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user'  => [
                'id'         => $user->id,
                'name'       => $user->name,
                'mobile'     => $user->mobile,
                'role'       => $user->role,
                'commission' => $user->commission,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }
}