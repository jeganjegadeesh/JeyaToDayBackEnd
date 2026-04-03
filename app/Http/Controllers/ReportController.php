<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\StockEntry;
use App\Models\ReturnStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * GET /reports/today
     * Today's summary
     */
    public function today(): JsonResponse
    {
        $today = today()->toDateString();

        $totalSales      = Bill::whereDate('date', $today)->sum('total_sales');
        $totalCommission = Bill::whereDate('date', $today)->sum('commission');
        $totalFinal      = Bill::whereDate('date', $today)->sum('final_amount');
        $totalBills      = Bill::whereDate('date', $today)->count();
        $totalStock      = StockEntry::whereDate('date', $today)->count();
        $totalReturns    = ReturnStock::whereDate('date', $today)->count();

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

    /**
     * GET /reports/chart/admin?mode=days|months
     *
     * mode=days   → last 7 days, each day total_sales
     * mode=months → last 4 months, each month total_sales
     *
     * Response:
     * {
     *   "chart": [
     *     { "label": "26/03", "value": 1500.00 },
     *     ...
     *   ]
     * }
     */
    public function adminChart(Request $request): JsonResponse
    {
        $mode = $request->get('mode', 'days'); // days | months

        if ($mode === 'months') {
            $data = [];
            for ($i = 3; $i >= 0; $i--) {
                $month     = Carbon::now()->subMonths($i)->startOfMonth();
                $fromDate  = $month->toDateString();
                $toDate    = $month->copy()->endOfMonth()->toDateString();
                $label     = $month->format('M');

                $totalSales = Bill::whereDate('date', '>=', $fromDate)
                    ->whereDate('date', '<=', $toDate)
                    ->sum('total_sales');

                $data[] = [
                    'label' => $label,
                    'value' => (float) $totalSales,
                ];
            }
        } else {
            // Last 7 days
            $data = [];
            for ($i = 6; $i >= 0; $i--) {
                $day   = Carbon::now()->subDays($i)->toDateString();
                $label = Carbon::now()->subDays($i)->format('d/m');

                $totalSales = Bill::whereDate('date', $day)->sum('total_sales');

                $data[] = [
                    'label' => $label,
                    'value' => (float) $totalSales,
                ];
            }
        }

        return response()->json(['chart' => $data]);
    }

    /**
     * GET /reports/chart/retailer?mode=days|months
     *
     * Uses the authenticated retailer's id automatically.
     * Returns earnings (final_amount) per day/month.
     *
     * Response:
     * {
     *   "chart": [
     *     { "label": "26/03", "value": 800.00 },
     *     ...
     *   ]
     * }
     */
    public function retailerChart(Request $request): JsonResponse
    {
        $retailerId = $request->user()->id;
        $mode       = $request->get('mode', 'days');

        if ($mode === 'months') {
            $data = [];
            for ($i = 3; $i >= 0; $i--) {
                $month    = Carbon::now()->subMonths($i)->startOfMonth();
                $fromDate = $month->toDateString();
                $toDate   = $month->copy()->endOfMonth()->toDateString();
                $label    = $month->format('M');

                $totalEarnings = Bill::where('retailer_id', $retailerId)
                    ->whereDate('date', '>=', $fromDate)
                    ->whereDate('date', '<=', $toDate)
                    ->sum('final_amount');

                $data[] = [
                    'label' => $label,
                    'value' => (float) $totalEarnings,
                ];
            }
        } else {
            // Last 7 days
            $data = [];
            for ($i = 6; $i >= 0; $i--) {
                $day   = Carbon::now()->subDays($i)->toDateString();
                $label = Carbon::now()->subDays($i)->format('d/m');

                $totalEarnings = Bill::where('retailer_id', $retailerId)
                    ->whereDate('date', $day)
                    ->sum('final_amount');

                $data[] = [
                    'label' => $label,
                    'value' => (float) $totalEarnings,
                ];
            }
        }

        return response()->json(['chart' => $data]);
    }
}
