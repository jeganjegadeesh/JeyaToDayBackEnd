<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /** GET /api/products?type=&search= */
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        return response()->json($query->latest()->paginate(20));
    }

    /** POST /api/products */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:retail,bulk,both',
            'rate' => 'required|numeric|min:0',
        ]);
        $data['created_by'] = $request->user()->id;

        $product = Product::create($data);

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }

    /** PUT /api/products/{product} */
    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:retail,bulk,both',
            'rate' => 'sometimes|numeric|min:0',
        ]);
        $data['updated_by'] = $request->user()->id;

        $product->update($data);

        return response()->json($product);
    }

    /** DELETE /api/products/{product} - Admin only (route middleware) */
    public function destroy(Product $product)
    {
        $product->softDeleteFlag();

        return response()->json(['message' => 'Product deleted.']);
    }

    public function restore(Product $product)
    {
        $product->restoreFlag();

        return response()->json(['message' => 'Product restored.']);
    }
}
