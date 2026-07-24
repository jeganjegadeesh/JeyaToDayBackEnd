<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\GiveStockItem;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\ReturnStockItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * GET /api/reports/sales?type=total|holding|retailer|holding_retailer&from=&to=&retailer_id=
     * "Sales" figures come from generated bills' subtotal (pre-commission), per dashboard convention.
     *
     * - total: last-30-days (or from/to) sales as a Date/Retailer/Amount table, plus last-month
     *   and this-month totals for context.
     * - holding: list of retailers currently holding stock (value not yet billed).
     * - retailer: a single retailer's own Date/Amount sales table (requires retailer_id).
     * - holding_retailer: a single retailer's current holding, broken down by product (requires retailer_id).
     */
    public function sales(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:total,holding,retailer,holding_retailer',
            'from' => 'sometimes|date',
            'to' => 'sometimes|date',
            'retailer_id' => 'required_if:type,retailer,holding_retailer|exists:retailers,id',
        ]);

        // Default window: last 30 days (inclusive of today).
        $to = $data['to'] ?? now()->toDateString();
        $from = $data['from'] ?? now()->subDays(29)->toDateString();

        return response()->json(match ($data['type']) {
            'total' => $this->totalSalesTable($from, $to),
            'holding' => $this->holdingSalesList(),
            'retailer' => $this->retailerSalesTable((int) $data['retailer_id'], $from, $to),
            'holding_retailer' => $this->holdingRetailerDetail((int) $data['retailer_id']),
        });
    }

    /**
     * Date / Retailer / Amount rows for the selected window (default last 30
     * days), plus last-calendar-month and this-calendar-month totals so the
     * table can show a comparison row at the top and a running total at the
     * bottom.
     */
    protected function totalSalesTable(string $from, string $to): array
    {
        $rows = Bill::whereBetween('date', [$from, $to])
            ->with('retailer:id,name')
            ->orderBy('date')
            ->get()
            ->map(fn ($bill) => [
                'date' => $bill->date->toDateString(),
                'retailer_id' => $bill->retailer_id,
                'retailer_name' => $bill->retailer->name ?? '-',
                'amount' => (float) $bill->subtotal,
            ])
            ->values();

        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $thisMonthStart = now()->startOfMonth()->toDateString();
        $thisMonthEnd = now()->toDateString();

        $lastMonthTotal = round(Bill::whereBetween('date', [$lastMonthStart, $lastMonthEnd])->sum('subtotal'), 2);
        $thisMonthTotal = round(Bill::whereBetween('date', [$thisMonthStart, $thisMonthEnd])->sum('subtotal'), 2);
        $periodTotal = round($rows->sum('amount'), 2);

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'last_month_total' => $lastMonthTotal,
            'this_month_total' => $thisMonthTotal,
            'period_total' => $periodTotal,
        ];
    }

    /** Every retailer currently holding unbilled stock, with its Rs. value. */
    protected function holdingSalesList(): array
    {
        $rows = $this->holdingValueByRetailer();
        $retailers = Retailer::whereIn('id', array_column($rows, 'retailer_id'))->pluck('name', 'id');

        $rows = array_map(fn ($r) => [
            'retailer_id' => $r['retailer_id'],
            'retailer_name' => $retailers[$r['retailer_id']] ?? '-',
            'holding_value' => $r['holding_value'],
        ], $rows);

        return [
            'rows' => $rows,
            'total_holding_value' => round(array_sum(array_column($rows, 'holding_value')), 2),
        ];
    }

    /** One retailer's own Date/Amount sales rows for the selected window. */
    protected function retailerSalesTable(int $retailerId, string $from, string $to): array
    {
        $retailer = Retailer::findOrFail($retailerId);

        $rows = Bill::where('retailer_id', $retailerId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get()
            ->map(fn ($bill) => ['date' => $bill->date->toDateString(), 'amount' => (float) $bill->subtotal])
            ->values();

        return [
            'retailer_id' => $retailer->id,
            'retailer_name' => $retailer->name,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'total_sales' => round($rows->sum('amount'), 2),
        ];
    }

    /** One retailer's current holding, broken down by product. */
    protected function holdingRetailerDetail(int $retailerId): array
    {
        $retailer = Retailer::findOrFail($retailerId);

        $given = GiveStockItem::join('give_stocks', 'give_stocks.id', '=', 'give_stock_items.give_stock_id')
            ->join('products', 'products.id', '=', 'give_stock_items.product_id')
            ->where('give_stocks.is_billed', 0)
            ->where('give_stocks.retailer_id', $retailerId)
            ->select('give_stock_items.product_id', 'products.name as product_name', 'products.rate')
            ->selectRaw('SUM(give_stock_items.quantity) as qty')
            ->groupBy('give_stock_items.product_id', 'products.name', 'products.rate')
            ->get()
            ->keyBy('product_id');

        $returned = ReturnStockItem::join('return_stocks', 'return_stocks.id', '=', 'return_stock_items.return_stock_id')
            ->where('return_stocks.is_billed', 0)
            ->where('return_stocks.retailer_id', $retailerId)
            ->select('return_stock_items.product_id')
            ->selectRaw('SUM(return_stock_items.quantity) as qty')
            ->groupBy('return_stock_items.product_id')
            ->pluck('qty', 'product_id');

        $rows = [];
        foreach ($given as $pid => $row) {
            $qty = round($row->qty - ($returned[$pid] ?? 0), 2);
            $rate = (float) $row->rate;
            $rows[] = [
                'product_id' => $pid,
                'product_name' => $row->product_name,
                'qty' => $qty,
                'rate' => $rate,
                'value' => round($qty * $rate, 2),
            ];
        }

        return [
            'retailer_id' => $retailer->id,
            'retailer_name' => $retailer->name,
            'rows' => $rows,
            'total_holding_value' => round(array_sum(array_column($rows, 'value')), 2),
        ];
    }

    /**
     * GET /api/reports/stock?type=total|holding|retailer|holding_retailer&from=&to=&retailer_id=
     * Mirrors the sales report shapes, but in quantity terms instead of Rs.
     *
     * - total: last-30-days (or from/to) stock sold as a Date/Retailer/Qty table, plus last-month
     *   and this-month totals for context.
     * - holding: list of retailers currently holding stock (qty not yet returned/billed).
     * - retailer: a single retailer's own Date/Qty sold table (requires retailer_id).
     * - holding_retailer: a single retailer's current holding, broken down by product (requires retailer_id).
     */
    public function stock(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:total,holding,retailer,holding_retailer',
            'from' => 'sometimes|date',
            'to' => 'sometimes|date',
            'retailer_id' => 'required_if:type,retailer,holding_retailer|exists:retailers,id',
        ]);

        // Default window: last 30 days (inclusive of today).
        $to = $data['to'] ?? now()->toDateString();
        $from = $data['from'] ?? now()->subDays(29)->toDateString();

        return response()->json(match ($data['type']) {
            'total' => $this->totalStockTable($from, $to),
            'holding' => $this->holdingStockList(),
            'retailer' => $this->retailerStockTable((int) $data['retailer_id'], $from, $to),
            'holding_retailer' => $this->holdingRetailerStockDetail((int) $data['retailer_id']),
        });
    }

    /**
     * Date / Retailer / Product / Qty rows for the selected window (default
     * last 30 days) - one row per product sold per bill, so stock can be
     * read product-wise - plus last-calendar-month and this-calendar-month
     * totals.
     */
    protected function totalStockTable(string $from, string $to): array
    {
        $bills = Bill::whereBetween('date', [$from, $to])
            ->with(['retailer:id,name', 'items.product:id,name'])
            ->orderBy('date')
            ->get();

        $rows = [];
        foreach ($bills as $bill) {
            foreach ($bill->items as $item) {
                $rows[] = [
                    'date' => $bill->date->toDateString(),
                    'retailer_id' => $bill->retailer_id,
                    'retailer_name' => $bill->retailer->name ?? '-',
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? '-',
                    'qty' => (float) $item->sold_qty,
                ];
            }
        }

        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $thisMonthStart = now()->startOfMonth()->toDateString();
        $thisMonthEnd = now()->toDateString();

        $lastMonthTotal = round(BillItem::join('bills', 'bills.id', '=', 'bill_items.bill_id')
            ->whereBetween('bills.date', [$lastMonthStart, $lastMonthEnd])
            ->sum('bill_items.sold_qty'), 2);
        $thisMonthTotal = round(BillItem::join('bills', 'bills.id', '=', 'bill_items.bill_id')
            ->whereBetween('bills.date', [$thisMonthStart, $thisMonthEnd])
            ->sum('bill_items.sold_qty'), 2);
        $periodTotal = round(array_sum(array_column($rows, 'qty')), 2);

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'last_month_total' => $lastMonthTotal,
            'this_month_total' => $thisMonthTotal,
            'period_total' => $periodTotal,
        ];
    }

    /** Product-wise total stock currently held across all retailers (qty not yet returned/billed). */
    protected function holdingStockList(): array
    {
        $rows = $this->holdingQtyByProduct();

        return [
            'rows' => $rows,
            'total_holding_qty' => round(array_sum(array_column($rows, 'holding_qty')), 2),
        ];
    }

    /** One retailer's own Date / Product / Qty sold rows for the selected window. */
    protected function retailerStockTable(int $retailerId, string $from, string $to): array
    {
        $retailer = Retailer::findOrFail($retailerId);

        $bills = Bill::where('retailer_id', $retailerId)
            ->whereBetween('date', [$from, $to])
            ->with('items.product:id,name')
            ->orderBy('date')
            ->get();

        $rows = [];
        foreach ($bills as $bill) {
            foreach ($bill->items as $item) {
                $rows[] = [
                    'date' => $bill->date->toDateString(),
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? '-',
                    'qty' => (float) $item->sold_qty,
                ];
            }
        }

        return [
            'retailer_id' => $retailer->id,
            'retailer_name' => $retailer->name,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'total_sold_qty' => round(array_sum(array_column($rows, 'qty')), 2),
        ];
    }

    /** One retailer's current holding, broken down by product (qty only, no Rs. value). */
    protected function holdingRetailerStockDetail(int $retailerId): array
    {
        $retailer = Retailer::findOrFail($retailerId);
        $rows = $this->holdingQtyByProductAndRetailer($retailerId);

        return [
            'retailer_id' => $retailer->id,
            'retailer_name' => $retailer->name,
            'rows' => $rows,
            'total_holding_qty' => round(collect($rows)->sum('holding_qty'), 2),
        ];
    }

    /** GET /api/reports/my/sales - Retailer's own product-wise + holding sales */
    public function mySales(Request $request)
    {
        $retailer = Retailer::where('user_id', $request->user()->id)->firstOrFail();

        $productWise = BillItem::join('bills', 'bills.id', '=', 'bill_items.bill_id')
            ->where('bills.retailer_id', $retailer->id)
            ->join('products', 'products.id', '=', 'bill_items.product_id')
            ->select('products.name as product_name')
            ->selectRaw('SUM(bill_items.amount) as total_amount')
            ->groupBy('products.id', 'products.name')
            ->get();

        $holding = $this->holdingValueByRetailer($retailer->id);

        return response()->json([
            'product_wise_sales' => $productWise,
            'holding_sales' => $holding,
        ]);
    }

    /** GET /api/reports/my/stock - Retailer's own stock reports */
    public function myStock(Request $request)
    {
        $retailer = Retailer::where('user_id', $request->user()->id)->firstOrFail();

        $productWiseSold = BillItem::join('bills', 'bills.id', '=', 'bill_items.bill_id')
            ->where('bills.retailer_id', $retailer->id)
            ->join('products', 'products.id', '=', 'bill_items.product_id')
            ->select('products.name as product_name')
            ->selectRaw('SUM(bill_items.sold_qty) as total_sold')
            ->groupBy('products.id', 'products.name')
            ->get();

        $holdingStock = $this->holdingQtyByProductAndRetailer($retailer->id);

        return response()->json([
            'product_wise_stock_sales' => $productWiseSold,
            'holding_stock' => $holdingStock,
            'product_wise_stock_having' => $holdingStock, // current balance per product
        ]);
    }

    /** Value (Rs.) of stock currently with retailers, not yet returned/billed. */
    protected function holdingValue(): float
    {
        $given = GiveStockItem::join('give_stocks', 'give_stocks.id', '=', 'give_stock_items.give_stock_id')
            ->where('give_stocks.is_billed', 0)
            ->join('products', 'products.id', '=', 'give_stock_items.product_id')
            ->selectRaw('SUM(give_stock_items.quantity * products.rate) as val')
            ->value('val') ?? 0;

        $returned = ReturnStockItem::join('return_stocks', 'return_stocks.id', '=', 'return_stock_items.return_stock_id')
            ->where('return_stocks.is_billed', 0)
            ->join('products', 'products.id', '=', 'return_stock_items.product_id')
            ->selectRaw('SUM(return_stock_items.quantity * products.rate) as val')
            ->value('val') ?? 0;

        return round($given - $returned, 2);
    }

    protected function holdingValueByRetailer(?int $retailerId = null)
    {
        $givenQuery = GiveStockItem::join('give_stocks', 'give_stocks.id', '=', 'give_stock_items.give_stock_id')
            ->where('give_stocks.is_billed', 0)
            ->join('products', 'products.id', '=', 'give_stock_items.product_id')
            ->select('give_stocks.retailer_id')
            ->selectRaw('SUM(give_stock_items.quantity * products.rate) as given_val')
            ->groupBy('give_stocks.retailer_id');

        $returnedQuery = ReturnStockItem::join('return_stocks', 'return_stocks.id', '=', 'return_stock_items.return_stock_id')
            ->where('return_stocks.is_billed', 0)
            ->join('products', 'products.id', '=', 'return_stock_items.product_id')
            ->select('return_stocks.retailer_id')
            ->selectRaw('SUM(return_stock_items.quantity * products.rate) as returned_val')
            ->groupBy('return_stocks.retailer_id');

        if ($retailerId) {
            $givenQuery->where('give_stocks.retailer_id', $retailerId);
            $returnedQuery->where('return_stocks.retailer_id', $retailerId);
        }

        $given = $givenQuery->pluck('given_val', 'retailer_id');
        $returned = $returnedQuery->pluck('returned_val', 'retailer_id');

        $retailerIds = $retailerId ? [$retailerId] : array_unique(array_merge($given->keys()->all(), $returned->keys()->all()));
        $result = [];
        foreach ($retailerIds as $rid) {
            $result[] = [
                'retailer_id' => $rid,
                'holding_value' => round(($given[$rid] ?? 0) - ($returned[$rid] ?? 0), 2),
            ];
        }

        return $retailerId ? ($result[0]['holding_value'] ?? 0) : $result;
    }

    protected function holdingQtyByProduct()
    {
        $given = GiveStockItem::join('give_stocks', 'give_stocks.id', '=', 'give_stock_items.give_stock_id')
            ->where('give_stocks.is_billed', 0)
            ->select('give_stock_items.product_id')
            ->selectRaw('SUM(give_stock_items.quantity) as qty')
            ->groupBy('give_stock_items.product_id')
            ->pluck('qty', 'product_id');

        $returned = ReturnStockItem::join('return_stocks', 'return_stocks.id', '=', 'return_stock_items.return_stock_id')
            ->where('return_stocks.is_billed', 0)
            ->select('return_stock_items.product_id')
            ->selectRaw('SUM(return_stock_items.quantity) as qty')
            ->groupBy('return_stock_items.product_id')
            ->pluck('qty', 'product_id');

        $productIds = array_unique(array_merge($given->keys()->all(), $returned->keys()->all()));
        $names = Product::whereIn('id', $productIds)->pluck('name', 'id');

        $result = [];
        foreach ($productIds as $pid) {
            $result[] = [
                'product_id' => $pid,
                'product_name' => $names[$pid] ?? '-',
                'holding_qty' => round(($given[$pid] ?? 0) - ($returned[$pid] ?? 0), 2),
            ];
        }

        return $result;
    }

    protected function holdingQtyByProductAndRetailer(?int $retailerId = null)
    {
        $givenQuery = GiveStockItem::join('give_stocks', 'give_stocks.id', '=', 'give_stock_items.give_stock_id')
            ->where('give_stocks.is_billed', 0)
            ->join('products', 'products.id', '=', 'give_stock_items.product_id')
            ->select('give_stock_items.product_id', 'products.name as product_name')
            ->selectRaw('SUM(give_stock_items.quantity) as qty');

        $returnedQuery = ReturnStockItem::join('return_stocks', 'return_stocks.id', '=', 'return_stock_items.return_stock_id')
            ->where('return_stocks.is_billed', 0)
            ->select('return_stock_items.product_id')
            ->selectRaw('SUM(return_stock_items.quantity) as qty');

        if ($retailerId) {
            $givenQuery->where('give_stocks.retailer_id', $retailerId);
            $returnedQuery->where('return_stocks.retailer_id', $retailerId);
        }

        $given = $givenQuery->groupBy('give_stock_items.product_id', 'products.name')->get()->keyBy('product_id');
        $returned = $returnedQuery->groupBy('return_stock_items.product_id')->pluck('qty', 'product_id');

        $result = [];
        foreach ($given as $pid => $row) {
            $result[] = [
                'product_id' => $pid,
                'product_name' => $row->product_name,
                'holding_qty' => round($row->qty - ($returned[$pid] ?? 0), 2),
            ];
        }

        return $result;
    }
}