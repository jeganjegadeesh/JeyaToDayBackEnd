<?php

namespace App\Http\Controllers;

use App\Models\CashPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashPaymentController extends Controller
{
    /**
     * GET /cash-payments
     */
    public function index(Request $request): JsonResponse
    {
        $payments = CashPayment::with('retailer:id,name')
            ->when($request->retailer_id,
                fn($q) => $q->where('retailer_id', $request->retailer_id))
            ->when($request->from_date,
                fn($q) => $q->whereDate('date', '>=', $request->from_date))
            ->when($request->to_date,
                fn($q) => $q->whereDate('date', '<=', $request->to_date))
            ->when(isset($request->is_billed),
                fn($q) => $q->where('is_billed', filter_var($request->is_billed, FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return response()->json(['payments' => $payments]);
    }

    /**
     * POST /cash-payments
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'retailer_id' => 'required|exists:users,id',
            'date'        => 'required|date',
            'amount'      => 'required|numeric|min:1',
            'note'        => 'nullable|string|max:255',
        ]);

        $payment = CashPayment::create([
            'retailer_id' => $request->retailer_id,
            'date'        => $request->date,
            'amount'      => $request->amount,
            'note'        => $request->note,
            'is_billed'   => false,
        ]);

        return response()->json([
            'message' => 'Cash payment recorded successfully.',
            'payment' => $payment->load('retailer:id,name'),
        ], 201);
    }

    /**
     * DELETE /cash-payments/{id}
     *
     * Blocked if the payment has already been included in a bill.
     */
    public function destroy(CashPayment $cashPayment): JsonResponse
    {
        if ($cashPayment->is_billed) {
            return response()->json([
                'message' => 'Cannot delete. This cash payment has already been included in a bill. Delete the bill first to make corrections.',
            ], 422);
        }

        $cashPayment->delete();

        return response()->json([
            'message' => 'Cash payment deleted successfully.'
        ]);
    }

    /**
     * GET /cash-payments/total
     *
     * Total unbilled cash received for a retailer (used for next bill preview).
     */
    public function total(Request $request): JsonResponse
    {
        $request->validate([
            'retailer_id' => 'required|exists:users,id',
        ]);

        $query = CashPayment::where('retailer_id', $request->retailer_id)
            ->where('is_billed', false);

        $total    = $query->sum('amount');
        $payments = CashPayment::where('retailer_id', $request->retailer_id)
            ->where('is_billed', false)
            ->orderBy('date')
            ->get();

        return response()->json([
            'total'    => $total,
            'payments' => $payments,
        ]);
    }
}