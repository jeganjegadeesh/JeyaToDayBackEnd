<?php

namespace App\Services;

use App\Exceptions\BillGenerationException;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\BillSettlement;
use App\Models\CashPayment;
use App\Models\GiveStock;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\ReturnStock;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Implements section 7 "Bill Calculation Logic" from the spec:
 *   1. Total Sold (per product)   = Given Qty - Returned Qty
 *   2. Product Amount             = Total Sold x Rate
 *   3. Subtotal                   = Sum of all Product Amounts
 *   4. Commission                 = Subtotal x Commission %
 *   5. Final Total                = Subtotal - Commission
 *   6. Cash Paid                  = Sum of all prior (unbilled) cash payments
 *   7. Grand Total                = Final Total - Cash Paid
 *
 * A bill only covers transactions since the last bill for that retailer
 * (give_stock / return_stock / cash_payment rows with is_billed = 0),
 * up to and including the chosen billing date.
 */
class BillCalculationService
{
    /**
     * Build a preview (or the data to persist) without saving anything.
     */
    public function calculate(Retailer $retailer, string $date): array
    {
        $giveStocks = GiveStock::where('retailer_id', $retailer->id)
            ->where('is_billed', 0)
            ->where('date', '<=', $date)
            ->with('items')
            ->get();

        // Without give-stock there's nothing to sell/bill for - generating
        // anyway would produce an empty bill with a grand total of Rs. 0.
        if ($giveStocks->isEmpty()) {
            throw new BillGenerationException(
                "No give-stock records found for {$retailer->name} up to {$date}. Please record give-stock for this retailer before generating a bill."
            );
        }

        $returnStocks = ReturnStock::where('retailer_id', $retailer->id)
            ->where('is_billed', 0)
            ->where('date', '<=', $date)
            ->with('items')
            ->get();

        $cashPayments = CashPayment::where('retailer_id', $retailer->id)
            ->where('is_billed', 0)
            ->where('date', '<=', $date)
            ->get();

        // Aggregate given & returned quantities per product.
        $givenByProduct = [];
        foreach ($giveStocks as $gs) {
            foreach ($gs->items as $item) {
                $givenByProduct[$item->product_id] = ($givenByProduct[$item->product_id] ?? 0) + (float) $item->quantity;
            }
        }

        $returnedByProduct = [];
        foreach ($returnStocks as $rs) {
            foreach ($rs->items as $item) {
                $returnedByProduct[$item->product_id] = ($returnedByProduct[$item->product_id] ?? 0) + (float) $item->quantity;
            }
        }

        $productIds = array_unique(array_merge(array_keys($givenByProduct), array_keys($returnedByProduct)));
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $lineItems = [];
        $subtotal = 0;
        foreach ($productIds as $productId) {
            $given = $givenByProduct[$productId] ?? 0;
            $returned = $returnedByProduct[$productId] ?? 0;
            $sold = $given - $returned;
            $rate = (float) ($products[$productId]->rate ?? 0);
            $amount = round($sold * $rate, 2);
            $subtotal += $amount;

            $lineItems[] = [
                'product_id' => $productId,
                'product_name' => $products[$productId]->name ?? '',
                'given_qty' => $given,
                'returned_qty' => $returned,
                'sold_qty' => $sold,
                'rate' => $rate,
                'amount' => $amount,
            ];
        }

        $subtotal = round($subtotal, 2);
        $commissionPercent = (float) $retailer->commission;
        $commissionAmount = round($subtotal * $commissionPercent / 100, 2);
        $finalTotal = round($subtotal - $commissionAmount, 2);
        $cashPaid = round($cashPayments->sum('amount'), 2);
        $grandTotal = round($finalTotal - $cashPaid, 2);

        return [
            'retailer_id' => $retailer->id,
            'retailer_name' => $retailer->name,
            'date' => $date,
            'line_items' => $lineItems,
            'subtotal' => $subtotal,
            'commission_percent' => $commissionPercent,
            'commission_amount' => $commissionAmount,
            'final_total' => $finalTotal,
            'cash_paid' => $cashPaid,
            'grand_total' => $grandTotal,
            '_give_stock_ids' => $giveStocks->pluck('id'),
            '_return_stock_ids' => $returnStocks->pluck('id'),
            '_cash_payment_ids' => $cashPayments->pluck('id'),
        ];
    }

    /**
     * Persist the bill: create Bill + BillItems, and flag the consumed
     * give_stock/return_stock/cash_payment rows as billed so the next
     * bill only picks up transactions after this one.
     *
     * If $cashCollectedNow is given (the confirmation step right after
     * generation - "did the retailer pay now?"), it is immediately applied
     * against the bill's outstanding grand total via settle().
     */
    public function generate(Retailer $retailer, string $date, ?float $cashCollectedNow = null): Bill
    {
        $data = $this->calculate($retailer, $date);

        $bill = DB::transaction(function () use ($data, $retailer, $date) {
            $bill = Bill::create([
                'company_id' => $retailer->company_id,
                'retailer_id' => $retailer->id,
                'date' => $date,
                'subtotal' => $data['subtotal'],
                'commission_percent' => $data['commission_percent'],
                'commission_amount' => $data['commission_amount'],
                'final_total' => $data['final_total'],
                'cash_paid' => $data['cash_paid'],
                'grand_total' => $data['grand_total'],
                'created_by' => Auth::id(),
            ]);

            foreach ($data['line_items'] as $li) {
                BillItem::create([
                    'bill_id' => $bill->id,
                    'product_id' => $li['product_id'],
                    'given_qty' => $li['given_qty'],
                    'returned_qty' => $li['returned_qty'],
                    'sold_qty' => $li['sold_qty'],
                    'rate' => $li['rate'],
                    'amount' => $li['amount'],
                ]);
            }

            GiveStock::whereIn('id', $data['_give_stock_ids'])->update(['is_billed' => 1, 'bill_id' => $bill->id]);
            ReturnStock::whereIn('id', $data['_return_stock_ids'])->update(['is_billed' => 1, 'bill_id' => $bill->id]);
            CashPayment::whereIn('id', $data['_cash_payment_ids'])->update(['is_billed' => 1, 'bill_id' => $bill->id]);

            return $bill->load('items.product', 'retailer');
        });

        if ($cashCollectedNow !== null && $cashCollectedNow > 0) {
            $bill = $this->settle($bill, $cashCollectedNow, $date);
        }

        return $bill;
    }

    /**
     * Record cash collected from the retailer against a bill's outstanding
     * grand total - either right after generation (the confirmation flow:
     * "did the retailer pay now?") or later once they actually pay.
     *
     * This does NOT touch the cash_payments table - that table is only for
     * the retailer's own advance/ad-hoc payments (auto-consumed into the
     * NEXT bill's "Cash Paid"). A settlement instead closes out THIS bill's
     * remaining balance directly: it's recorded in bill_settlements and the
     * bill's own grand_total is reduced immediately (down to Rs. 0 for a
     * full settlement), so the bill itself reflects what's still owed.
     */
    public function settle(Bill $bill, float $amount, ?string $date = null): Bill
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new BillGenerationException('Settlement amount must be greater than zero.');
        }

        $outstanding = round((float) $bill->grand_total, 2);
        if ($amount > $outstanding + 0.01) {
            throw new BillGenerationException("Settlement amount cannot exceed the outstanding balance of Rs. {$outstanding}.");
        }

        return DB::transaction(function () use ($bill, $amount, $outstanding, $date) {
            BillSettlement::create([
                'company_id' => $bill->company_id,
                'bill_id' => $bill->id,
                'retailer_id' => $bill->retailer_id,
                'date' => $date ?? Carbon::now()->toDateString(),
                'amount' => $amount,
                'created_by' => Auth::id(),
            ]);

            $bill->cash_paid = round((float) $bill->cash_paid + $amount, 2);
            $bill->grand_total = round($outstanding - $amount, 2);
            $bill->save();

            return $bill->fresh(['items.product', 'retailer']);
        });
    }
}