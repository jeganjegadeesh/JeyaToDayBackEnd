<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\CashPayment;
use App\Models\GiveStock;
use App\Models\Retailer;
use App\Models\ReturnStock;
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

        // What the retailer still owes the company across all their bills
        // (Bill::grand_total is reduced as settlements come in, so summing
        // it gives the live outstanding balance rather than a snapshot).
        $currentBalance = (float) Bill::where('retailer_id', $retailer->id)->sum('grand_total');

        return response()->json([
            'period' => $period,
            'earnings_chart' => $chart,
            'current_balance' => round($currentBalance, 2),
            'recent_transactions' => $this->recentTransactions($retailer),
        ]);
    }

    /**
     * Merge the retailer's last few bills, stock movements, and cash
     * payments into a single "Recent Transactions" feed for the dashboard,
     * newest first.
     *
     * Deliberately returns generic fields (kind/ref_id/status/items_count)
     * rather than pre-rendered English strings like "Invoice #3" or
     * "Balance due" - the Flutter app builds the localized title/subtitle
     * itself so the feed reads correctly in every supported language.
     */
    private function recentTransactions(Retailer $retailer, int $limit = 8): array
    {
        $items = collect();

        foreach (Bill::where('retailer_id', $retailer->id)->latest('date')->take($limit)->get() as $bill) {
            $items->push([
                'kind' => 'bill',
                'ref_id' => $bill->id,
                'status' => ((float) $bill->grand_total > 0.005) ? 'due' : 'settled',
                'items_count' => null,
                'amount' => (float) $bill->final_total,
                'date' => optional($bill->date)->toDateString(),
            ]);
        }

        foreach (GiveStock::where('retailer_id', $retailer->id)->withCount('items')->latest('date')->take($limit)->get() as $gs) {
            $items->push([
                'kind' => 'stock_in',
                'ref_id' => $gs->id,
                'status' => null,
                'items_count' => $gs->items_count,
                'amount' => null,
                'date' => optional($gs->date)->toDateString(),
            ]);
        }

        foreach (ReturnStock::where('retailer_id', $retailer->id)->withCount('items')->latest('date')->take($limit)->get() as $rs) {
            $items->push([
                'kind' => 'stock_out',
                'ref_id' => $rs->id,
                'status' => null,
                'items_count' => $rs->items_count,
                'amount' => null,
                'date' => optional($rs->date)->toDateString(),
            ]);
        }

        foreach (CashPayment::where('retailer_id', $retailer->id)->latest('date')->take($limit)->get() as $cp) {
            $items->push([
                'kind' => 'payment',
                'ref_id' => $cp->id,
                'status' => null,
                'items_count' => null,
                'amount' => (float) $cp->amount,
                'date' => optional($cp->date)->toDateString(),
            ]);
        }

        return $items->sortByDesc('date')->take($limit)->values()->all();
    }
}