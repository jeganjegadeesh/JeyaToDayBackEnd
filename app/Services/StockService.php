<?php

namespace App\Services;

use App\Models\StockEntry;
use App\Models\ReturnStock;

class StockService
{
    public function giveStock(int $retailerId, string $date, array $items): StockEntry
    {
        // Delete existing entry for same retailer and date if exists
        $existing = StockEntry::where('retailer_id', $retailerId)
            ->whereDate('date', $date)
            ->first();

        if ($existing) {
            $existing->items()->delete();
            $existing->delete();
        }

        // Create new stock entry
        $entry = StockEntry::create([
            'retailer_id' => $retailerId,
            'date'        => $date,
        ]);

        // Create stock entry items
        foreach ($items as $item) {
            $entry->items()->create([
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
            ]);
        }

        return $entry;
    }

    public function recordReturn(int $retailerId, string $date, array $items): ReturnStock
    {
        // Delete existing return for same retailer and date if exists
        $existing = ReturnStock::where('retailer_id', $retailerId)
            ->whereDate('date', $date)
            ->first();

        if ($existing) {
            $existing->items()->delete();
            $existing->delete();
        }

        // Create new return
        $return = ReturnStock::create([
            'retailer_id' => $retailerId,
            'date'        => $date,
        ]);

        // Create return items
        foreach ($items as $item) {
            $return->items()->create([
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
            ]);
        }

        return $return;
    }
}