<?php

namespace App\Services;

use App\Models\StockEntry;
use App\Models\ReturnStock;

class StockService
{
    /**
     * Give stock to a retailer.
     *
     * Block rule: only reject if the EXISTING entry for this retailer+date
     * has already been billed (is_billed = true).
     * A new entry on the same date as a previous billed entry is allowed —
     * it simply creates a fresh unbilled record for the next bill cycle.
     */
    public function giveStock(
        int $retailerId,
        string $date,
        array $items
    ): StockEntry {
        // Block only if an already-billed entry exists for this retailer+date.
        $billedExists = StockEntry::where('retailer_id', $retailerId)
            ->whereDate('date', $date)
            ->where('is_billed', true)
            ->exists();

        if ($billedExists) {
            throw new \Exception(
                'Cannot edit stock. This stock entry has already been included in a bill.'
            );
        }

        // Replace any existing UNBILLED entry for this date.
        $existing = StockEntry::where('retailer_id', $retailerId)
            ->whereDate('date', $date)
            ->where('is_billed', false)
            ->first();

        if ($existing) {
            $existing->items()->delete();
            $existing->delete();
        }

        $entry = StockEntry::create([
            'retailer_id' => $retailerId,
            'date'        => $date,
            'is_billed'   => false,
        ]);

        foreach ($items as $item) {
            $entry->items()->create([
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
            ]);
        }

        return $entry;
    }

    /**
     * Record a return from a retailer.
     *
     * Same rule: only block if the existing return for this date is billed.
     * A new unbilled return on the same date is always allowed.
     */
    public function recordReturn(
        int $retailerId,
        string $date,
        array $items
    ): ReturnStock {
        $billedExists = ReturnStock::where('retailer_id', $retailerId)
            ->whereDate('date', $date)
            ->where('is_billed', true)
            ->exists();

        if ($billedExists) {
            throw new \Exception(
                'Cannot edit returns. This return entry has already been included in a bill.'
            );
        }

        // Replace any existing UNBILLED return for this date.
        $existing = ReturnStock::where('retailer_id', $retailerId)
            ->whereDate('date', $date)
            ->where('is_billed', false)
            ->first();

        if ($existing) {
            $existing->items()->delete();
            $existing->delete();
        }

        $return = ReturnStock::create([
            'retailer_id' => $retailerId,
            'date'        => $date,
            'is_billed'   => false,
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