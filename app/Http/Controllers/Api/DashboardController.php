<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\CashPayment;
use App\Models\Expense;
use App\Models\Retailer;
use App\Models\RetailerLoan;
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

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $salesToday = (float) Bill::whereDate('date', $today)->sum('subtotal');
        $salesYesterday = (float) Bill::whereDate('date', $yesterday)->sum('subtotal');
        $changePercent = $salesYesterday > 0
            ? round((($salesToday - $salesYesterday) / $salesYesterday) * 100, 1)
            : ($salesToday > 0 ? 100.0 : 0.0);

        $pendingLoans = (float) (RetailerLoan::selectRaw('SUM(amount - repaid_amount) as total')->value('total') ?? 0);

        return response()->json([
            'period' => $period,
            'sales_chart' => $chart,
            'total_retailers' => Retailer::count(),
            'total_bills_this_month' => Bill::whereMonth('date', now()->month)->whereYear('date', now()->year)->count(),
            'total_sales_today' => $salesToday,
            'sales_change_percent' => $changePercent,
            'pending_loans' => $pendingLoans,
            'recent_transactions' => $this->recentTransactions(),
        ]);
    }

    /**
     * A single, chronologically-merged feed of the last 5 cash-related
     * events across bills (sales), cash payments received, and expenses
     * paid out — each tagged with a `kind` so the app can colour/sign the
     * amount correctly (neutral sale, blue income, red expense).
     */
    private function recentTransactions()
    {
        $take = 5;

        $sales = Bill::with('retailer:id,name')
            ->orderByDesc('date')->orderByDesc('id')->take($take)
            ->get(['id', 'retailer_id', 'date', 'final_total', 'created_at'])
            ->map(fn (Bill $b) => [
                'kind' => 'sale',
                'title' => $b->retailer?->name ?? 'Retailer #'.$b->retailer_id,
                'subtitle' => null,
                'date' => optional($b->date)->format('Y-m-d'),
                'timestamp' => optional($b->created_at)->toIso8601String(),
                'amount' => (float) $b->final_total,
            ]);

        $cashPayments = CashPayment::with('retailer:id,name')
            ->orderByDesc('date')->orderByDesc('id')->take($take)
            ->get(['id', 'retailer_id', 'date', 'amount', 'created_at'])
            ->map(fn (CashPayment $c) => [
                'kind' => 'income',
                'title' => 'Cash Payment',
                'subtitle' => $c->retailer?->name,
                'date' => optional($c->date)->format('Y-m-d'),
                'timestamp' => optional($c->created_at)->toIso8601String(),
                'amount' => (float) $c->amount,
            ]);

        $expenses = Expense::orderByDesc('date')->orderByDesc('id')->take($take)
            ->get(['id', 'date', 'amount', 'remarks', 'created_at'])
            ->map(fn (Expense $e) => [
                'kind' => 'expense',
                'title' => $e->remarks ?: 'Expense',
                'subtitle' => null,
                'date' => optional($e->date)->format('Y-m-d'),
                'timestamp' => optional($e->created_at)->toIso8601String(),
                'amount' => (float) $e->amount,
            ]);

        return $sales->concat($cashPayments)->concat($expenses)
            ->sortByDesc(fn ($row) => $row['timestamp'] ?? $row['date'])
            ->values()
            ->take($take);
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