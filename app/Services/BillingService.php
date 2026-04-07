<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\CashPayment;
use App\Models\StockEntry;
use App\Models\ReturnStock;
use App\Models\User;

class BillingService
{
    /**
     * Generate a bill for a retailer.
     *
     * Collects ALL unbilled stock entries, returns, and cash payments
     * for this retailer (is_billed = false), calculates the bill, then
     * marks every included record with is_billed = true and bill_id.
     *
     * No date-range filtering — the bill covers everything unbilled.
     */
    public function generateBill(int $retailerId): Bill
    {
        $retailer = User::findOrFail($retailerId);

        // ── 1. Collect all UNBILLED records ──────────────────────────────

        $stockEntries = StockEntry::with('items.product')
            ->where('retailer_id', $retailerId)
            ->where('is_billed', false)
            ->get();

        $returnEntries = ReturnStock::with('items')
            ->where('retailer_id', $retailerId)
            ->where('is_billed', false)
            ->get();

        $cashPayments = CashPayment::where('retailer_id', $retailerId)
            ->where('is_billed', false)
            ->get();

        $paidAmount = $cashPayments->sum('amount');

        // ── 2. Build given map [product_id => total_given_qty] ────────────

        $givenMap   = [];
        $productMap = [];
        foreach ($stockEntries as $entry) {
            foreach ($entry->items as $item) {
                $pid             = $item->product_id;
                $givenMap[$pid]  = ($givenMap[$pid] ?? 0) + $item->quantity;
                $productMap[$pid] ??= $item->product;
            }
        }

        // ── 3. Build return map [product_id => total_returned_qty] ────────

        $returnMap = [];
        foreach ($returnEntries as $entry) {
            foreach ($entry->items as $item) {
                $pid             = $item->product_id;
                $returnMap[$pid] = ($returnMap[$pid] ?? 0) + $item->quantity;
            }
        }

        // ── 4. Calculate bill totals ──────────────────────────────────────

        $totalSales = 0;
        $billItems  = [];

        foreach ($givenMap as $productId => $givenQty) {
            $returnedQty = $returnMap[$productId] ?? 0;
            $soldQty     = $givenQty - $returnedQty;
            $price       = $productMap[$productId]->price ?? 0;
            $amount      = $soldQty * $price;

            $totalSales += $amount;

            $billItems[] = [
                'product_id'   => $productId,
                'given_qty'    => $givenQty,
                'returned_qty' => $returnedQty,
                'sold_qty'     => $soldQty,
                'price'        => $price,
                'amount'       => $amount,
            ];
        }

        $commissionPercent = $retailer->commission;
        $commission        = $totalSales * $commissionPercent / 100;
        $finalAmount       = $totalSales - $commission;
        $balanceAmount     = $finalAmount - $paidAmount;

        // ── 5. Determine date range from the collected records ────────────

        $allDates = collect();
        foreach ($stockEntries as $e) { $allDates->push($e->date); }
        foreach ($returnEntries as $r) { $allDates->push($r->date); }
        foreach ($cashPayments   as $c) { $allDates->push($c->date); }

        $fromDate = $allDates->min()?->toDateString() ?? now()->toDateString();
        $toDate   = $allDates->max()?->toDateString() ?? now()->toDateString();

        // ── 6. Create the bill ────────────────────────────────────────────

        $bill = Bill::create([
            'retailer_id'    => $retailerId,
            'date'           => $toDate,
            'from_date'      => $fromDate,
            'to_date'        => $toDate,
            'total_sales'    => $totalSales,
            'commission'     => $commission,
            'final_amount'   => $finalAmount,
            'paid_amount'    => $paidAmount,
            'balance_amount' => $balanceAmount,
        ]);

        foreach ($billItems as $item) {
            $bill->items()->create($item);
        }

        // ── 7. Mark all included records as billed ────────────────────────

        $stockEntries->each(fn($e) => $e->update(['is_billed' => true, 'bill_id' => $bill->id]));
        $returnEntries->each(fn($r) => $r->update(['is_billed' => true, 'bill_id' => $bill->id]));
        $cashPayments->each(fn($c)  => $c->update(['is_billed' => true, 'bill_id' => $bill->id]));

        return $bill;
    }

    /**
     * Delete a bill and reset all linked records back to unbilled.
     * This allows corrections and re-generating the bill.
     */
    public function deleteBill(Bill $bill): void
    {
        StockEntry::where('bill_id', $bill->id)
            ->update(['is_billed' => false, 'bill_id' => null]);

        ReturnStock::where('bill_id', $bill->id)
            ->update(['is_billed' => false, 'bill_id' => null]);

        CashPayment::where('bill_id', $bill->id)
            ->update(['is_billed' => false, 'bill_id' => null]);

        $bill->items()->delete();
        $bill->delete();
    }

    /**
     * Get pending (unbilled) summary for a retailer.
     * Used by Flutter to preview what the next bill will include.
     */
    public function getPendingSummary(int $retailerId): array
    {
        $stockEntries  = StockEntry::where('retailer_id', $retailerId)->where('is_billed', false)->orderBy('date')->get();
        $returnEntries = ReturnStock::where('retailer_id', $retailerId)->where('is_billed', false)->orderBy('date')->get();
        $cashPayments  = CashPayment::where('retailer_id', $retailerId)->where('is_billed', false)->orderBy('date')->get();

        $allDates = collect();
        foreach ($stockEntries  as $e) { $allDates->push($e->date); }
        foreach ($returnEntries as $r) { $allDates->push($r->date); }
        foreach ($cashPayments  as $c) { $allDates->push($c->date); }

        return [
            'from_date'      => $allDates->min()?->toDateString(),
            'to_date'        => $allDates->max()?->toDateString(),
            'stock_entries'  => $stockEntries->count(),
            'return_entries' => $returnEntries->count(),
            'cash_payments'  => $cashPayments->count(),
            'paid_amount'    => $cashPayments->sum('amount'),
        ];
    }
}