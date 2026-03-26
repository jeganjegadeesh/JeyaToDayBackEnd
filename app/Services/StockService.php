<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\StockEntry;
use App\Models\ReturnStock;

class StockService
{
    public function giveStock(
        int $retailerId,
        string $date,
        array $items
    ): StockEntry {
        // Check if any bill exists covering this date
        $billExists = Bill::where('retailer_id', $retailerId)
            ->where('from_date', '<=', $date)
            ->where('to_date', '>=', $date)
            ->exists();

        if ($billExists) {
            throw new \Exception(
                'Cannot edit stock. Bill already generated for this date.'
            );
        }

        $existing = StockEntry::where(
            'retailer_id', $retailerId
        )
            ->whereDate('date', $date)
            ->first();

        if ($existing) {
            $existing->items()->delete();
            $existing->delete();
        }

        $entry = StockEntry::create([
            'retailer_id' => $retailerId,
            'date'        => $date,
        ]);

        foreach ($items as $item) {
            $entry->items()->create([
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
            ]);
        }

        return $entry;
    }

    public function recordReturn(
        int $retailerId,
        string $date,
        array $items
    ): ReturnStock {
        // Check if any bill exists covering this date
        $billExists = Bill::where('retailer_id', $retailerId)
            ->where('from_date', '<=', $date)
            ->where('to_date', '>=', $date)
            ->exists();

        if ($billExists) {
            throw new \Exception(
                'Cannot edit returns. Bill already generated for this date.'
            );
        }

        $existing = ReturnStock::where(
            'retailer_id', $retailerId
        )
            ->whereDate('date', $date)
            ->first();

        if ($existing) {
            $existing->items()->delete();
            $existing->delete();
        }

        $return = ReturnStock::create([
            'retailer_id' => $retailerId,
            'date'        => $date,
        ]);

        foreach ($items as $item) {
            $return->items()->create([
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
            ]);
        }

        return $return;
    }
}