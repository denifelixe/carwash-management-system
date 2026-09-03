<?php

namespace App\Actions\Admin;

use App\Models\DailyBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class UpdateDailyBalance
{
    /**
     * Apply movements for one date and rebuild all later accumulated balances.
     */
    public function handle(
        string $date,
        int $cashIncomeDelta = 0,
        int $cashExpenseDelta = 0,
        int $nonCashIncomeDelta = 0,
        int $nonCashExpenseDelta = 0,
    ): void {
        if ($cashIncomeDelta === 0
            && $cashExpenseDelta === 0
            && $nonCashIncomeDelta === 0
            && $nonCashExpenseDelta === 0) {
            return;
        }

        DB::transaction(function () use (
            $date,
            $cashIncomeDelta,
            $cashExpenseDelta,
            $nonCashIncomeDelta,
            $nonCashExpenseDelta,
        ): void {
            DailyBalance::query()
                ->orderBy('date')
                ->lockForUpdate()
                ->get();

            DB::table((new DailyBalance)->getTable())->insertOrIgnore([
                'date' => $date,
                'cash_income' => 0,
                'cash_expense' => 0,
                'cash_balance' => 0,
                'non_cash_income' => 0,
                'non_cash_expense' => 0,
                'non_cash_balance' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $dailyBalance = DailyBalance::query()
                ->whereDate('date', $date)
                ->lockForUpdate()
                ->firstOrFail();
            $totals = [
                'cash_income' => $dailyBalance->cash_income + $cashIncomeDelta,
                'cash_expense' => $dailyBalance->cash_expense + $cashExpenseDelta,
                'non_cash_income' => $dailyBalance->non_cash_income + $nonCashIncomeDelta,
                'non_cash_expense' => $dailyBalance->non_cash_expense + $nonCashExpenseDelta,
            ];

            if (collect($totals)->contains(fn (int $total): bool => $total < 0)) {
                throw new LogicException('Saldo harian tidak dapat membalik transaksi yang belum tercatat.');
            }

            $dailyBalance->update($totals);

            $previous = DailyBalance::query()
                ->whereDate('date', '<', $date)
                ->latest('date')
                ->first(['cash_balance', 'non_cash_balance']);
            $cashBalance = $previous?->cash_balance ?? 0;
            $nonCashBalance = $previous?->non_cash_balance ?? 0;

            DailyBalance::query()
                ->whereDate('date', '>=', $date)
                ->orderBy('date')
                ->lockForUpdate()
                ->get()
                ->each(function (DailyBalance $snapshot) use (&$cashBalance, &$nonCashBalance): void {
                    $cashBalance += $snapshot->cash_income - $snapshot->cash_expense;
                    $nonCashBalance += $snapshot->non_cash_income - $snapshot->non_cash_expense;

                    if ($snapshot->cash_balance !== $cashBalance
                        || $snapshot->non_cash_balance !== $nonCashBalance) {
                        $snapshot->update([
                            'cash_balance' => $cashBalance,
                            'non_cash_balance' => $nonCashBalance,
                        ]);
                    }
                });
        });
    }

    /**
     * @param  list<array{label: string, amount: int, reference?: string}>  $channels
     * @return array{cash: int, nonCash: int}
     */
    public static function channelAmounts(array $channels): array
    {
        $cash = 0;
        $nonCash = 0;

        foreach ($channels as $channel) {
            if (Str::before($channel['label'], ' · ') === 'Tunai') {
                $cash += $channel['amount'];
            } else {
                $nonCash += $channel['amount'];
            }
        }

        return ['cash' => $cash, 'nonCash' => $nonCash];
    }

    /** @return array{cash: int, nonCash: int} */
    public static function methodAmounts(string $method, int $amount): array
    {
        return $method === 'Tunai'
            ? ['cash' => $amount, 'nonCash' => 0]
            : ['cash' => 0, 'nonCash' => $amount];
    }
}
