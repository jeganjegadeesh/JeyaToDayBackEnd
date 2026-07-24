<?php

namespace App\Services;

use App\Models\BillSettlement;
use App\Models\CashPayment;
use App\Models\Company;
use App\Models\Expense;
use App\Models\RetailerLoan;
use Carbon\Carbon;

/**
 * Implements the Cash Report as a transaction ledger (credit / debit),
 * carrying a running balance across the selected range:
 *
 *   Opening Balance
 *   + credit: cash payments received from retailers (advance or bill settlement)
 *   - debit:  raw material expenses
 *   - debit:  retailer loan amounts
 *
 * Supports filters: today, yesterday, this_week, this_month, custom.
 *
 * IMPORTANT: `company.opening_balance` is a one-time figure that applies
 * only to `company.opening_balance_date` (the single day it was entered,
 * e.g. "1 July => 25000"). It must NOT be re-added on every report. Instead:
 *
 *   - On opening_balance_date itself, the day's opening balance = opening_balance.
 *   - Every day after that, the day's opening balance = the PREVIOUS day's
 *     closing balance (previous opening + that day's income - that day's expense).
 *   - This "carry forward" is computed for whatever range is requested
 *     (today / yesterday / this_week / this_month / custom) by rolling
 *     forward from opening_balance_date up to the day before the range starts.
 */
class CashReportService
{
    public function build(Company $company, string $filter = 'today', ?string $from = null, ?string $to = null): array
    {
        [$start, $end] = $this->resolveRange($filter, $from, $to);

        $openingBalance = $this->openingBalanceForRange($company, $start);

        $entries = $this->collectEntries($start, $end);

        $running = $openingBalance;
        $totalCredit = 0.0;
        $totalDebit = 0.0;
        $transactions = [];

        foreach ($entries as $entry) {
            $signed = $entry['type'] === 'credit' ? $entry['amount'] : -$entry['amount'];
            $running = round($running + $signed, 2);

            if ($entry['type'] === 'credit') {
                $totalCredit += $entry['amount'];
            } else {
                $totalDebit += $entry['amount'];
            }

            unset($entry['_sort']);
            $entry['running_balance'] = $running;
            $transactions[] = $entry;
        }

        return [
            'filter' => $filter,
            'from' => $start,
            'to' => $end,
            'opening_balance' => round($openingBalance, 2),
            'transactions' => $transactions,
            'total_credit' => round($totalCredit, 2),
            'total_debit' => round($totalDebit, 2),
            'closing_balance' => $running,

            // Kept for backward compatibility with any existing consumers.
            'cash_payments_received' => round($totalCredit, 2),
            'raw_material_expenses' => round((float) Expense::whereBetween('date', [$start, $end])->sum('amount'), 2),
            'retailer_loan_amounts' => round((float) RetailerLoan::whereBetween('date', [$start, $end])->sum('amount'), 2),
            'other_income' => 0,
            'other_expenses' => 0,
            'total_income' => round($openingBalance + $totalCredit, 2),
            'total_expense' => round($totalDebit, 2),
            'current_balance' => $running,
        ];
    }

    /**
     * Pulls every ledger-worthy row in the range (cash payments - both plain
     * advance payments and bill settlements, raw material expenses, retailer
     * loans) and returns them as normalized credit/debit entries, sorted by
     * date then creation order.
     */
    protected function collectEntries(string $start, string $end): array
    {
        $entries = [];

        $cashPayments = CashPayment::with('retailer')->whereBetween('date', [$start, $end])->get();
        foreach ($cashPayments as $p) {
            $entries[] = [
                'date' => $p->date->toDateString(),
                'type' => 'credit',
                'category' => 'cash_payment',
                'retailer_id' => $p->retailer_id,
                'retailer_name' => $p->retailer->name ?? null,
                'bill_id' => null,
                'remarks' => null,
                'amount' => round((float) $p->amount, 2),
                '_sort' => $p->date->toDateString().'-cp-'.str_pad((string) $p->id, 10, '0', STR_PAD_LEFT),
            ];
        }

        $billSettlements = BillSettlement::with('retailer')->whereBetween('date', [$start, $end])->get();
        foreach ($billSettlements as $s) {
            $entries[] = [
                'date' => $s->date->toDateString(),
                'type' => 'credit',
                'category' => 'bill_settlement',
                'retailer_id' => $s->retailer_id,
                'retailer_name' => $s->retailer->name ?? null,
                'bill_id' => $s->bill_id,
                'remarks' => null,
                'amount' => round((float) $s->amount, 2),
                '_sort' => $s->date->toDateString().'-bs-'.str_pad((string) $s->id, 10, '0', STR_PAD_LEFT),
            ];
        }

        $expenses = Expense::whereBetween('date', [$start, $end])->get();
        foreach ($expenses as $e) {
            $entries[] = [
                'date' => $e->date->toDateString(),
                'type' => 'debit',
                'category' => 'raw_material_expense',
                'retailer_id' => null,
                'retailer_name' => null,
                'bill_id' => null,
                'remarks' => $e->remarks,
                'amount' => round((float) $e->amount, 2),
                '_sort' => $e->date->toDateString().'-'.str_pad((string) $e->id, 10, '0', STR_PAD_LEFT),
            ];
        }

        $retailerLoans = RetailerLoan::with('retailer')->whereBetween('date', [$start, $end])->get();
        foreach ($retailerLoans as $l) {
            $entries[] = [
                'date' => $l->date->toDateString(),
                'type' => 'debit',
                'category' => 'retailer_loan',
                'retailer_id' => $l->retailer_id,
                'retailer_name' => $l->retailer->name ?? null,
                'bill_id' => null,
                'remarks' => $l->remarks,
                'amount' => round((float) $l->amount, 2),
                '_sort' => $l->date->toDateString().'-'.str_pad((string) $l->id, 10, '0', STR_PAD_LEFT),
            ];
        }

        usort($entries, fn ($a, $b) => $a['_sort'] <=> $b['_sort']);

        return $entries;
    }

    /**
     * The balance that should be treated as "opening balance" for a report
     * that starts on $rangeStart.
     *
     *  - No opening_balance_date set yet            => 0 (nothing entered).
     *  - $rangeStart is BEFORE opening_balance_date  => 0 (no data that far back).
     *  - $rangeStart == opening_balance_date         => the raw opening_balance figure.
     *  - $rangeStart is AFTER opening_balance_date   => opening_balance rolled
     *    forward day-by-day (i.e. + all income - all expense recorded from
     *    opening_balance_date up to the day BEFORE $rangeStart).
     */
    protected function openingBalanceForRange(Company $company, string $rangeStart): float
    {
        $obDate = $company->opening_balance_date;

        if (! $obDate) {
            return 0.0;
        }

        $obDate = Carbon::parse($obDate)->toDateString();
        $rangeStart = Carbon::parse($rangeStart)->toDateString();

        if ($rangeStart < $obDate) {
            return 0.0;
        }

        if ($rangeStart === $obDate) {
            return (float) $company->opening_balance;
        }

        $carryEnd = Carbon::parse($rangeStart)->subDay()->toDateString();

        $incomeSoFar = (float) CashPayment::whereBetween('date', [$obDate, $carryEnd])->sum('amount')
            + (float) BillSettlement::whereBetween('date', [$obDate, $carryEnd])->sum('amount');
        $expenseSoFar = (float) Expense::whereBetween('date', [$obDate, $carryEnd])->sum('amount')
            + (float) RetailerLoan::whereBetween('date', [$obDate, $carryEnd])->sum('amount');

        return round((float) $company->opening_balance + $incomeSoFar - $expenseSoFar, 2);
    }

    protected function resolveRange(string $filter, ?string $from, ?string $to): array
    {
        return match ($filter) {
            'today' => [Carbon::today()->toDateString(), Carbon::today()->toDateString()],
            'yesterday' => [Carbon::yesterday()->toDateString(), Carbon::yesterday()->toDateString()],
            'this_week' => [Carbon::now()->startOfWeek()->toDateString(), Carbon::now()->endOfWeek()->toDateString()],
            'this_month' => [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()],
            'custom' => [$from ?? Carbon::today()->toDateString(), $to ?? Carbon::today()->toDateString()],
            default => [Carbon::today()->toDateString(), Carbon::today()->toDateString()],
        };
    }
}