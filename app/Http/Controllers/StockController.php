<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockGiveRequest;
use App\Models\StockEntry;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(private StockService $stockService) {}

    /**
     * POST /stock/give
     */
    public function give(StockGiveRequest $request): JsonResponse
    {
        $entry = $this->stockService->giveStock(
            $request->retailer_id,
            $request->date,
            $request->items
        );

        return response()->json([
            'message' => 'Stock distributed successfully.',
            'entry'   => $entry->load('items.product', 'retailer:id,name'),
        ], 201);
    }

    /**
     * GET /stock/history
     */
    public function history(Request $request): JsonResponse
    {
        $entries = StockEntry::with('items.product', 'retailer:id,name,mobile')
            ->when($request->retailer_id, fn($q) => $q->where('retailer_id', $request->retailer_id))
            ->when($request->date,        fn($q) => $q->whereDate('date', $request->date))
            ->when($request->from_date,   fn($q) => $q->whereDate('date', '>=', $request->from_date))
            ->when($request->to_date,     fn($q) => $q->whereDate('date', '<=', $request->to_date))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return response()->json(['entries' => $entries]);
    }

    /**
     * GET /stock/today/{retailerId}
     */
    public function todayStock(int $retailerId): JsonResponse
    {
        $entry = StockEntry::with('items.product')
            ->where('retailer_id', $retailerId)
            ->whereDate('date', today())
            ->first();

        return response()->json(['entry' => $entry]);
    }
}