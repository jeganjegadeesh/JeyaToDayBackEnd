<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function __construct(
        private BillingService $billingService
    ) {}

    /**
     * POST /bill/generate
     *
     * No from_date / to_date needed — bill covers all unbilled records.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'retailer_id' => 'required|exists:users,id',
        ]);

        $bill = $this->billingService->generateBill(
            $request->retailer_id,
        );

        return response()->json([
            'message' => 'Bill generated successfully.',
            'bill'    => $bill->load(
                'items.product',
                'retailer:id,name,commission'
            ),
        ], 201);
    }

    /**
     * GET /bill/pending/{retailerId}
     *
     * Preview of what will be included in the next bill.
     */
    public function pending(int $retailerId): JsonResponse
    {
        $summary = $this->billingService->getPendingSummary($retailerId);

        return response()->json(['pending' => $summary]);
    }

    /**
     * GET /bill/history
     */
    public function history(Request $request): JsonResponse
    {
        $bills = Bill::with(
            'items.product',
            'retailer:id,name,mobile,commission'
        )
            ->when($request->retailer_id,
                fn($q) => $q->where('retailer_id', $request->retailer_id))
            ->when($request->from_date,
                fn($q) => $q->whereDate('date', '>=', $request->from_date))
            ->when($request->to_date,
                fn($q) => $q->whereDate('date', '<=', $request->to_date))
            ->orderByDesc('id')
            ->get();

        return response()->json(['bills' => $bills]);
    }

    /**
     * GET /bill/{id}
     */
    public function show(Bill $bill): JsonResponse
    {
        return response()->json([
            'bill' => $bill->load(
                'items.product',
                'retailer:id,name,mobile,commission'
            ),
        ]);
    }

    /**
     * GET /bill/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $bills = Bill::with('retailer:id,name')
            ->when($request->from_date,
                fn($q) => $q->whereDate('date', '>=', $request->from_date))
            ->when($request->to_date,
                fn($q) => $q->whereDate('date', '<=', $request->to_date))
            ->get();

        return response()->json([
            'summary' => [
                'total_sales'      => $bills->sum('total_sales'),
                'total_commission' => $bills->sum('commission'),
                'total_final'      => $bills->sum('final_amount'),
                'total_paid'       => $bills->sum('paid_amount'),
                'total_balance'    => $bills->sum('balance_amount'),
                'total_bills'      => $bills->count(),
            ],
            'bills' => $bills,
        ]);
    }

    /**
     * DELETE /bill/{id}
     *
     * Deletes the bill AND resets all linked records back to unbilled,
     * so they can be corrected and re-billed.
     */
    public function destroy(Bill $bill): JsonResponse
    {
        $this->billingService->deleteBill($bill);

        return response()->json([
            'message' => 'Bill deleted. All linked records are now unbilled and can be corrected.'
        ]);
    }
}