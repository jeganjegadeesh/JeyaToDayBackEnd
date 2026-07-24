<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawMaterial;
use Illuminate\Http\Request;

class RawMaterialController extends Controller
{
    public function index(Request $request)
    {
        $query = RawMaterial::query();
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $data['created_by'] = $request->user()->id;

        return response()->json(RawMaterial::create($data), 201);
    }

    public function show(RawMaterial $rawMaterial)
    {
        return response()->json($rawMaterial);
    }

    public function update(Request $request, RawMaterial $rawMaterial)
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $data['updated_by'] = $request->user()->id;
        $rawMaterial->update($data);

        return response()->json($rawMaterial);
    }

    /** Admin only */
    public function destroy(RawMaterial $rawMaterial)
    {
        $rawMaterial->softDeleteFlag();

        return response()->json(['message' => 'Raw material deleted.']);
    }
}
