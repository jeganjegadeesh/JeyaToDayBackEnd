<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiveStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GiveStockController extends Controller
{
    /** GET /api/give-stock?retailer_id=&date_from=&date_to=&is_billed=&per_page= */
    public function index(Request $request)
    {
        $query = GiveStock::with('items.product', 'retailer');

        if ($request->filled('retailer_id')) {
            $query->where('retailer_id', $request->retailer_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }
        // Useful for the bill-generate screen: is_billed=0 -> only pending
        // (not-yet-billed) entries for the retailer/date range being billed.
        if ($request->filled('is_billed')) {
            $query->where('is_billed', $request->boolean('is_billed'));
        }

        $perPage = (int) $request->input('per_page', 20);

        return response()->json($query->latest('date')->paginate($perPage));
    }

    /**
     * POST /api/give-stock
     * {
     *   retailer_id, date,
     *   items: [ { product_id, quantity }, ... ]
     * }
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'retailer_id' => 'required|exists:retailers,id',
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $giveStock = DB::transaction(function () use ($data, $request) {
            $header = GiveStock::create([
                'company_id' => $request->user()->company_id,
                'retailer_id' => $data['retailer_id'],
                'date' => $data['date'],
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                $header->items()->create($item);
            }

            return $header->load('items.product', 'retailer');
        });

        return response()->json($giveStock, 201);
    }

    public function show(GiveStock $giveStock)
    {
        return response()->json($giveStock->load('items.product', 'retailer'));
    }

    /** PUT /api/give-stock/{giveStock} - only editable if not yet billed */
    public function update(Request $request, GiveStock $giveStock)
    {
        if ($giveStock->is_billed) {
            return response()->json(['message' => 'Cannot edit a give-stock entry that has already been billed.'], 422);
        }

        $data = $request->validate([
            'date' => 'sometimes|date',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
        ]);

        DB::transaction(function () use ($data, $giveStock, $request) {
            if (isset($data['date'])) {
                $giveStock->update(['date' => $data['date'], 'updated_by' => $request->user()->id]);
            }
            if (isset($data['items'])) {
                $giveStock->items()->delete();
                foreach ($data['items'] as $item) {
                    $giveStock->items()->create($item);
                }
            }
        });

        return response()->json($giveStock->load('items.product', 'retailer'));
    }

    /** DELETE /api/give-stock/{giveStock} - Admin only, blocked once billed */
    public function destroy(GiveStock $giveStock)
    {
        if ($giveStock->is_billed) {
            return response()->json(['message' => 'Cannot delete a give-stock entry that has already been billed.'], 422);
        }

        $giveStock->softDeleteFlag();

        return response()->json(['message' => 'Give stock entry deleted.']);
    }
}