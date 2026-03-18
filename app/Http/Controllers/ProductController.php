<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /products
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json(['products' => $products]);
    }

    /**
     * POST /products
     */
    public function store(ProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $product,
        ], 201);
    }

    /**
     * GET /products/{id}
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json(['product' => $product]);
    }

    /**
     * PUT /products/{id}
     */
    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $product->fresh(),
        ]);
    }

    /**
     * DELETE /products/{id}
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully.']);
    }

    /**
     * GET /products/categories
     */
    public function categories(): JsonResponse
    {
        $categories = Product::distinct()->pluck('category')->filter()->values();

        return response()->json(['categories' => $categories]);
    }
}