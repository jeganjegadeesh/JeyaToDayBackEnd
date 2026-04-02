<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\CashPayment;
use App\Models\StockEntry;
use App\Models\ReturnStock;
use App\Models\User;

class BillingService
{
    public function generateBill(
        int $retailerId,
        string $fromDate,
        string $toDate
    ): Bill {
        $retailer = User::findOrFail($retailerId);

        // Get ALL stock entries in date range
        $stockEntries = StockEntry::with('items.product')
            ->where('retailer_id', $retailerId)
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->get();

        // Get ALL returns in date range
        $returnEntries = ReturnStock::with('items')
            ->where('retailer_id', $retailerId)
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->get();

        // Get ALL cash payments in date range
        $paidAmount = CashPayment::where('retailer_id', $retailerId)
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->sum('amount');

        // Build given map [product_id => total_given_qty]
        $givenMap   = [];
        $productMap = [];
        foreach ($stockEntries as $entry) {
            foreach ($entry->items as $item) {
                $pid = $item->product_id;
                $givenMap[$pid] =
                    ($givenMap[$pid] ?? 0) + $item->quantity;
                if (!isset($productMap[$pid])) {
                    $productMap[$pid] = $item->product;
                }
            }
        }

        // Build return map [product_id => total_returned_qty]
        $returnMap = [];
        foreach ($returnEntries as $entry) {
            foreach ($entry->items as $item) {
                $pid = $item->product_id;
                $returnMap[$pid] =
                    ($returnMap[$pid] ?? 0) + $item->quantity;
            }
        }

        // Delete existing bill for same retailer and date range
        Bill::where('retailer_id', $retailerId)
            ->where('from_date', $fromDate)
            ->where('to_date', $toDate)
            ->each(function ($bill) {
                $bill->items()->delete();
                $bill->delete();
            });

        // Calculate bill items
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

        // Commission
        $commissionPercent = $retailer->commission;
        $commission        = $totalSales * $commissionPercent / 100;
        $finalAmount       = $totalSales - $commission;
        $balanceAmount     = $finalAmount - $paidAmount;

        // Create bill
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

        return $bill;
    }

    /**
     * Get last bill date for a retailer
     * Used to auto-set from_date in Flutter
     */
    public function getLastBillDate(int $retailerId): ?string
    {
        $lastBill = Bill::where('retailer_id', $retailerId)
            ->orderByDesc('to_date')
            ->first();

        if ($lastBill) {
            // Next day after last bill
            return \Carbon\Carbon::parse($lastBill->to_date)
                ->addDay()
                ->toDateString();
        }

        // First ever bill - get first stock entry date
        $firstStock = StockEntry::where('retailer_id', $retailerId)
            ->orderBy('date')
            ->first();

        return $firstStock?->date?->toDateString();
    }
}