<?php

namespace App\Actions\Admin;

use App\Models\CashEntry;
use App\Models\DailyBalance;
use App\Models\OrderTransaction;
use App\Support\Admin\PaymentChannelBreakdown;
use Illuminate\Support\Facades\DB;

class RecalculateDailyBalances
{
    /**
     * Rebuild every snapshot from one business date using active source rows.
     */
    public function handle(string $fromDate): void
    {
        DB::transaction(function () use ($fromDate): void {
            DailyBalance::query()->orderBy('date')->lockForUpdate()->get();

            /** @var array<string, array{cash_income: int, cash_expense: int, non_cash_income: int, non_cash_expense: int}> $daily */
            $daily = [];
            $emptyDay = static fn (): array => [
                'cash_income' => 0,
                'cash_expense' => 0,
                'non_cash_income' => 0,
                'non_cash_expense' => 0,
            ];

            OrderTransaction::query()
                ->whereDate('paid_at', '>=', $fromDate)
                ->orderBy('id')
                ->get(['amount', 'channel_breakdown', 'paid_at'])
                ->each(function (OrderTransaction $transaction) use (&$daily, $emptyDay): void {
                    $date = $transaction->paid_at->toDateString();
                    $daily[$date] ??= $emptyDay();
                    $amounts = UpdateDailyBalance::channelAmounts(
                        PaymentChannelBreakdown::financial(
                            $transaction->channel_breakdown,
                            (int) $transaction->amount,
                        ),
                    );
                    $daily[$date]['cash_income'] += $amounts['cash'];
                    $daily[$date]['non_cash_income'] += $amounts['nonCash'];
                });

            CashEntry::query()
                ->whereDate('entry_date', '>=', $fromDate)
                ->orderBy('id')
                ->get(['direction', 'amount', 'method', 'entry_date'])
                ->each(function (CashEntry $entry) use (&$daily, $emptyDay): void {
                    $date = $entry->entry_date->toDateString();
                    $daily[$date] ??= $emptyDay();
                    $prefix = $entry->method === 'Tunai' ? 'cash' : 'non_cash';
                    $suffix = $entry->direction === 'out' ? 'expense' : 'income';
                    $daily[$date]["{$prefix}_{$suffix}"] += (int) $entry->amount;
                });

            ksort($daily);

            $previous = DailyBalance::query()
                ->whereDate('date', '<', $fromDate)
                ->latest('date')
                ->first(['cash_balance', 'non_cash_balance']);
            $cashBalance = 0;
            $nonCashBalance = 0;

            if ($previous !== null) {
                $cashBalance = (int) $previous->cash_balance;
                $nonCashBalance = (int) $previous->non_cash_balance;
            }
            $now = now();
            $rows = [];

            foreach ($daily as $date => $totals) {
                $cashBalance += $totals['cash_income'] - $totals['cash_expense'];
                $nonCashBalance += $totals['non_cash_income'] - $totals['non_cash_expense'];
                $rows[] = [
                    'date' => $date,
                    ...$totals,
                    'cash_balance' => $cashBalance,
                    'non_cash_balance' => $nonCashBalance,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DailyBalance::query()->whereDate('date', '>=', $fromDate)->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table((new DailyBalance)->getTable())->insert($chunk);
            }
        });
    }
}
