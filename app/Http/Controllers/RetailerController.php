<?php

namespace App\Http\Controllers;

use App\Http\Requests\RetailerRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class RetailerController extends Controller
{
    /**
     * GET /retailers
     */
    public function index(): JsonResponse
    {
        $retailers = User::retailers()
            ->select('id', 'name', 'mobile', 'commission', 'created_at')
            ->orderBy('name')
            ->get();

        return response()->json(['retailers' => $retailers]);
    }

    /**
     * POST /retailers
     */
    public function store(RetailerRequest $request): JsonResponse
    {
        $retailer = User::create([
            'name'       => $request->name,
            'mobile'     => $request->mobile,
            'password'   => Hash::make($request->password),
            'role'       => 'retailer',
            'commission' => $request->commission ?? 0,
        ]);

        return response()->json([
            'message'  => 'Retailer created successfully.',
            'retailer' => $retailer->only(['id', 'name', 'mobile', 'commission']),
        ], 201);
    }

    /**
     * GET /retailers/{id}
     */
    public function show(User $retailer): JsonResponse
    {
        abort_if($retailer->role !== 'retailer', 404, 'Retailer not found.');

        return response()->json([
            'retailer' => $retailer->only(['id', 'name', 'mobile', 'commission', 'created_at']),
        ]);
    }

    /**
     * PUT /retailers/{id}
     */
    public function update(RetailerRequest $request, User $retailer): JsonResponse
    {
        abort_if($retailer->role !== 'retailer', 404, 'Retailer not found.');

        $data = $request->only(['name', 'mobile', 'commission']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $retailer->update($data);

        return response()->json([
            'message'  => 'Retailer updated successfully.',
            'retailer' => $retailer->fresh()->only(['id', 'name', 'mobile', 'commission']),
        ]);
    }

    /**
     * DELETE /retailers/{id}
     */
    public function destroy(User $retailer): JsonResponse
    {
        abort_if($retailer->role !== 'retailer', 404, 'Retailer not found.');

        $retailer->delete();

        return response()->json(['message' => 'Retailer deleted successfully.']);
    }
}