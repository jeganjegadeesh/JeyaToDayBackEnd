<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillGenerateRequest;
use App\Models\Bill;
use App\Models\StockEntry;
use App\Models\ReturnStock;
use App\Models\Product;
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
     * Generate bill for a date range
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'retailer_id' => 'required|exists:users,id',
            'from_date'   => 'required|date',
            'to_date'     => 'required|date|after_or_equal:from_date',
        ]);

        $bill = $this->billingService->generateBill(
            $request->retailer_id,
            $request->from_date,
            $request->to_date,
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
     * GET /bill/history
     */
    public function history(Request $request): JsonResponse
    {
        $bills = Bill::with(
            'items.product',
            'retailer:id,name,mobile,commission'
        )
            ->when(
                $request->retailer_id,
                fn($q) => $q->where(
                    'retailer_id',
                    $request->retailer_id
                )
            )
            ->when(
                $request->date,
                fn($q) => $q->whereDate('date', $request->date)
            )
            ->when(
                $request->from_date,
                fn($q) => $q->whereDate(
                    'date', '>=', $request->from_date
                )
            )
            ->when(
                $request->to_date,
                fn($q) => $q->whereDate(
                    'date', '<=', $request->to_date
                )
            )
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
        $query = Bill::with('retailer:id,name')
            ->when(
                $request->from_date,
                fn($q) => $q->whereDate(
                    'date', '>=', $request->from_date
                )
            )
            ->when(
                $request->to_date,
                fn($q) => $q->whereDate(
                    'date', '<=', $request->to_date
                )
            );

        $bills = $query->get();

        $summary = [
            'total_sales'      => $bills->sum('total_sales'),
            'total_commission' => $bills->sum('commission'),
            'total_final'      => $bills->sum('final_amount'),
            'total_bills'      => $bills->count(),
        ];

        return response()->json([
            'summary' => $summary,
            'bills'   => $bills,
        ]);
    }

    /**
     * DELETE /bill/{id}
     */
    public function destroy(Bill $bill): JsonResponse
    {
        $bill->items()->delete();
        $bill->delete();

        return response()->json([
            'message' => 'Bill deleted successfully.'
        ]);
    }
}