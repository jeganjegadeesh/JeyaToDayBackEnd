<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReturnRequest;
use App\Models\ReturnStock;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function __construct(private StockService $stockService) {}

    /**
     * POST /returns
     */
    public function store(ReturnRequest $request): JsonResponse
    {
        $return = $this->stockService->recordReturn(
            $request->retailer_id,
            $request->date,
            $request->items
        );

        return response()->json([
            'message' => 'Returns recorded successfully.',
            'return'  => $return->load('items.product', 'retailer:id,name'),
        ], 201);
    }

    /**
     * GET /returns
     */
    public function index(Request $request): JsonResponse
    {
        $returns = ReturnStock::with('items.product', 'retailer:id,name,mobile')
            ->when($request->retailer_id, fn($q) => $q->where('retailer_id', $request->retailer_id))
            ->when($request->date,        fn($q) => $q->whereDate('date', $request->date))
            ->when($request->from_date,   fn($q) => $q->whereDate('date', '>=', $request->from_date))
            ->when($request->to_date,     fn($q) => $q->whereDate('date', '<=', $request->to_date))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return response()->json(['returns' => $returns]);
    }

    /**
     * GET /returns/today/{retailerId}
     */
    public function todayReturn(int $retailerId): JsonResponse
    {
        $return = ReturnStock::with('items.product')
            ->where('retailer_id', $retailerId)
            ->whereDate('date', today())
            ->first();

        return response()->json(['return' => $return]);
    }
}