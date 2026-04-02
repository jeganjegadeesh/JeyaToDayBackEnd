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
        ]);

        return response()->json([
            'message' => 'Cash payment recorded successfully.',
            'payment' => $payment->load('retailer:id,name'),
        ], 201);
    }

    /**
     * DELETE /cash-payments/{id}
     */
    public function destroy(CashPayment $cashPayment): JsonResponse
    {
        $cashPayment->delete();

        return response()->json([
            'message' => 'Cash payment deleted successfully.'
        ]);
    }

    /**
     * GET /cash-payments/total
     * Get total unpaid amount for a retailer between dates
     */
    public function total(Request $request): JsonResponse
    {
        $request->validate([
            'retailer_id' => 'required|exists:users,id',
            'from_date'   => 'required|date',
            'to_date'     => 'required|date',
        ]);

        $total = CashPayment::where('retailer_id', $request->retailer_id)
            ->whereDate('date', '>=', $request->from_date)
            ->whereDate('date', '<=', $request->to_date)
            ->sum('amount');

        $payments = CashPayment::where('retailer_id', $request->retailer_id)
            ->whereDate('date', '>=', $request->from_date)
            ->whereDate('date', '<=', $request->to_date)
            ->orderBy('date')
            ->get();

        return response()->json([
            'total'    => $total,
            'payments' => $payments,
        ]);
    }
}