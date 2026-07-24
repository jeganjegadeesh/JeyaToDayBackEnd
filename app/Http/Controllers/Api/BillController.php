<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BillGenerationException;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Retailer;
use App\Services\BillCalculationService;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function __construct(protected BillCalculationService $billService) {}

    /** GET /api/bills?retailer_id=&date_from=&date_to= */
    public function index(Request $request)
    {
        $query = Bill::with('retailer', 'items.product');

        if ($request->filled('retailer_id')) {
            $query->where('retailer_id', $request->retailer_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        return response()->json($query->latest('date')->paginate(20));
    }

    /**
     * POST /api/bills/preview  { retailer_id, date }
     * Returns the calculated breakdown WITHOUT saving anything.
     */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'retailer_id' => 'required|exists:retailers,id',
            'date' => 'required|date',
        ]);

        $retailer = Retailer::findOrFail($data['retailer_id']);

        try {
            return response()->json($this->billService->calculate($retailer, $data['date']));
        } catch (BillGenerationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/bills/generate  { retailer_id, date, cash_collected_now? }
     * Persists the bill and marks the underlying transactions as billed.
     * If cash_collected_now is provided (the "did the retailer pay now?"
     * confirmation step), it is immediately applied against the bill.
     */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'retailer_id' => 'required|exists:retailers,id',
            'date' => 'required|date',
            'cash_collected_now' => 'sometimes|nullable|numeric|min:0',
        ]);

        $retailer = Retailer::findOrFail($data['retailer_id']);

        try {
            $bill = $this->billService->generate($retailer, $data['date'], $data['cash_collected_now'] ?? null);
        } catch (BillGenerationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($bill, 201);
    }

    /**
     * POST /api/bills/{bill}/settle  { amount }
     * Record cash collected from the retailer against this bill's
     * outstanding grand total (full or partial settlement, any time
     * after generation).
     */
    public function settle(Request $request, Bill $bill)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $bill = $this->billService->settle($bill, $data['amount']);
        } catch (BillGenerationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($bill);
    }

    /** GET /api/bills/{bill} - full preview structure for printing */
    public function show(Bill $bill)
    {
        return response()->json($bill->load('items.product', 'retailer', 'retailer.company'));
    }

    /** DELETE /api/bills/{bill} - Admin only; reverts consumed txns back to unbilled */
    public function destroy(Bill $bill)
    {
        $bill->giveStocks()->update(['is_billed' => 0, 'bill_id' => null]);
        $bill->returnStocks()->update(['is_billed' => 0, 'bill_id' => null]);
        $bill->cashPayments()->update(['is_billed' => 0, 'bill_id' => null]);

        $bill->softDeleteFlag();

        return response()->json(['message' => 'Bill deleted and underlying transactions unlocked.']);
    }
}