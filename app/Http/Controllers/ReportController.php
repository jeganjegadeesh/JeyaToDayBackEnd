<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\StockEntry;
use App\Models\ReturnStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * GET /reports/today
     * Today's summary
     */
    public function today(): JsonResponse
    {
        $today = today()->toDateString();

        $totalSales     = Bill::whereDate('date', $today)->sum('total_sales');
        $totalCommission = Bill::whereDate('date', $today)->sum('commission');
        $totalFinal     = Bill::whereDate('date', $today)->sum('final_amount');
        $totalBills     = Bill::whereDate('date', $today)->count();
        $totalStock     = StockEntry::whereDate('date', $today)->count();
        $totalReturns   = ReturnStock::whereDate('date', $today)->count();

        $bills = Bill::with('retailer:id,name')
            ->whereDate('date', $today)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'summary' => [
                'total_sales'      => $totalSales,
                'total_commission' => $totalCommission,
                'total_final'      => $totalFinal,
                'total_bills'      => $totalBills,
                'total_stock'      => $totalStock,
                'total_returns'    => $totalReturns,
                'date'             => $today,
            ],
            'bills' => $bills,
        ]);
    }

    /**
     * GET /reports/summary
     * Summary with date filter
     */
    public function summary(Request $request): JsonResponse
    {
        $fromDate = $request->from_date ?? today()->startOfMonth()->toDateString();
        $toDate   = $request->to_date ?? today()->toDateString();

        $bills = Bill::with('retailer:id,name,commission')
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->orderByDesc('date')
            ->get();

        $summary = [
            'total_sales'      => $bills->sum('total_sales'),
            'total_commission' => $bills->sum('commission'),
            'total_final'      => $bills->sum('final_amount'),
            'total_bills'      => $bills->count(),
            'from_date'        => $fromDate,
            'to_date'          => $toDate,
        ];

        // Per retailer summary
        $retailerSummary = $bills->groupBy('retailer_id')->map(function ($retailerBills) {
            return [
                'retailer'         => $retailerBills->first()->retailer,
                'total_sales'      => $retailerBills->sum('total_sales'),
                'total_commission' => $retailerBills->sum('commission'),
                'total_final'      => $retailerBills->sum('final_amount'),
                'total_bills'      => $retailerBills->count(),
            ];
        })->values();

        // Daily summary
        $dailySummary = $bills->groupBy('date')->map(function ($dayBills, $date) {
            return [
                'date'        => $date,
                'total_sales' => $dayBills->sum('total_sales'),
                'total_final' => $dayBills->sum('final_amount'),
                'total_bills' => $dayBills->count(),
            ];
        })->values();

        return response()->json([
            'summary'          => $summary,
            'retailer_summary' => $retailerSummary,
            'daily_summary'    => $dailySummary,
            'bills'            => $bills,
        ]);
    }

    /**
     * GET /reports/retailer/{id}
     * Retailer specific report
     */
    public function retailerReport(int $retailerId, Request $request): JsonResponse
    {
        $fromDate = $request->from_date ?? today()->startOfMonth()->toDateString();
        $toDate   = $request->to_date ?? today()->toDateString();

        $bills = Bill::with('items.product', 'retailer:id,name,commission')
            ->where('retailer_id', $retailerId)
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->orderByDesc('date')
            ->get();

        $stockEntries = StockEntry::with('items.product')
            ->where('retailer_id', $retailerId)
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->orderByDesc('date')
            ->get();

        $returns = ReturnStock::with('items.product')
            ->where('retailer_id', $retailerId)
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->orderByDesc('date')
            ->get();

        return response()->json([
            'summary' => [
                'total_sales'      => $bills->sum('total_sales'),
                'total_commission' => $bills->sum('commission'),
                'total_final'      => $bills->sum('final_amount'),
                'total_bills'      => $bills->count(),
                'from_date'        => $fromDate,
                'to_date'          => $toDate,
            ],
            'bills'         => $bills,
            'stock_entries' => $stockEntries,
            'returns'       => $returns,
        ]);
    }
}