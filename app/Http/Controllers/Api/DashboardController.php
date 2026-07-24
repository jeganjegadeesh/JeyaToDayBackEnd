<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Retailer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /** GET /api/dashboard - Admin/Manager: Sales chart switchable monthly/weekly */
    public function index(Request $request)
    {
        $period = $request->get('period', 'monthly'); // monthly | weekly

        $query = Bill::query();
        $chart = $period === 'weekly'
            ? $query->where('date', '>=', Carbon::now()->subWeeks(8))
                ->selectRaw('YEARWEEK(date) as period, SUM(subtotal) as sales')
                ->groupBy('period')->orderBy('period')->get()
            : $query->where('date', '>=', Carbon::now()->subMonths(12))
                ->selectRaw("DATE_FORMAT(date, '%Y-%m') as period, SUM(subtotal) as sales")
                ->groupBy('period')->orderBy('period')->get();

        return response()->json([
            'period' => $period,
            'sales_chart' => $chart,
            'total_retailers' => Retailer::count(),
            'total_bills_this_month' => Bill::whereMonth('date', now()->month)->whereYear('date', now()->year)->count(),
        ]);
    }

    /** GET /api/dashboard/retailer - Retailer's own earnings chart */
    public function retailer(Request $request)
    {
        $retailer = Retailer::where('user_id', $request->user()->id)->firstOrFail();
        $period = $request->get('period', 'monthly');

        $query = Bill::where('retailer_id', $retailer->id);
        $chart = $period === 'weekly'
            ? $query->where('date', '>=', Carbon::now()->subWeeks(8))
                ->selectRaw('YEARWEEK(date) as period, SUM(final_total) as earnings')
                ->groupBy('period')->orderBy('period')->get()
            : $query->where('date', '>=', Carbon::now()->subMonths(12))
                ->selectRaw("DATE_FORMAT(date, '%Y-%m') as period, SUM(final_total) as earnings")
                ->groupBy('period')->orderBy('period')->get();

        return response()->json([
            'period' => $period,
            'earnings_chart' => $chart,
        ]);
    }
}
