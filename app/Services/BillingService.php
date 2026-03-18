<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\StockEntry;
use App\Models\ReturnStock;
use App\Models\User;

class BillingService
{
    public function generateBill(int $retailerId, string $date): Bill
    {
        // Get retailer commission
        $retailer = User::findOrFail($retailerId);

        // Get stock entry for this retailer and date
        $stockEntry = StockEntry::with('items.product')
            ->where('retailer_id', $retailerId)
            ->whereDate('date', $date)
            ->first();

        // Get return for this retailer and date
        $returnStock = ReturnStock::with('items')
            ->where('retailer_id', $retailerId)
            ->whereDate('date', $date)
            ->first();

        // Build return items map [product_id => quantity]
        $returnMap = [];
        if ($returnStock) {
            foreach ($returnStock->items as $item) {
                $returnMap[$item->product_id] = $item->quantity;
            }
        }

        // Delete existing bill for same retailer and date
        $existingBill = Bill::where('retailer_id', $retailerId)
            ->whereDate('date', $date)
            ->first();

        if ($existingBill) {
            $existingBill->items()->delete();
            $existingBill->delete();
        }

        // Calculate bill items
        $totalSales = 0;
        $billItems  = [];

        if ($stockEntry) {
            foreach ($stockEntry->items as $stockItem) {
                $productId   = $stockItem->product_id;
                $givenQty    = $stockItem->quantity;
                $returnedQty = $returnMap[$productId] ?? 0;
                $soldQty     = $givenQty - $returnedQty;
                $price       = $stockItem->product->price;
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
        }

        // Calculate commission and final amount
        $commissionPercent = $retailer->commission;
        $commission        = $totalSales * $commissionPercent / 100;
        $finalAmount       = $totalSales - $commission;

        // Create bill
        $bill = Bill::create([
            'retailer_id'  => $retailerId,
            'date'         => $date,
            'total_sales'  => $totalSales,
            'commission'   => $commission,
            'final_amount' => $finalAmount,
        ]);

        // Create bill items
        foreach ($billItems as $item) {
            $bill->items()->create($item);
        }

        return $bill;
    }
}