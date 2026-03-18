<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillGenerateRequest;
use App\Models\Bill;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function __construct(private BillingService $billingService) {}

    /**
     * POST /bill/generate
     */
    public function generate(BillGenerateRequest $request): JsonResponse
    {
        $bill = $this->billingService->generateBill(
            $request->retailer_id,
            $request->date
        );

        return response()->json([
            'message' => 'Bill generated successfully.',
            'bill'    => $bill->load('items.product', 'retailer:id,name,commission'),
        ], 201);
    }

    /**
     * GET /bill/history
     */
    public function history(Request $request): JsonResponse
    {
        $bills = Bill::with('retailer:id,name,mobile')
            ->when($request->retailer_id, fn($q) => $q->where('retailer_id', $request->retailer_id))
            ->when($request->date,        fn($q) => $q->whereDate('date', $request->date))
            ->when($request->from_date,   fn($q) => $q->whereDate('date', '>=', $request->from_date))
            ->when($request->to_date,     fn($q) => $q->whereDate('date', '<=', $request->to_date))
            ->orderByDesc('date')
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
            'bill' => $bill->load('items.product', 'retailer:id,name,mobile,commission'),
        ]);
    }

    /**
     * GET /bill/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $query = Bill::with('retailer:id,name')
            ->when($request->from_date, fn($q) => $q->whereDate('date', '>=', $request->from_date))
            ->when($request->to_date,   fn($q) => $q->whereDate('date', '<=', $request->to_date));

        $bills = $query->get();

        $summary = [
            'total_sales'    => $bills->sum('total_sales'),
            'total_commission' => $bills->sum('commission'),
            'total_final'    => $bills->sum('final_amount'),
            'total_bills'    => $bills->count(),
        ];

        return response()->json([
            'summary' => $summary,
            'bills'   => $bills,
        ]);
    }
}