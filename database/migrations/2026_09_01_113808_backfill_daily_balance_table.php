<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * @var array<string, array{cash_income: int, cash_expense: int, non_cash_income: int, non_cash_expense: int}> $daily
         */
        $daily = [];
        $emptyDay = fn (): array => [
            'cash_income' => 0,
            'cash_expense' => 0,
            'non_cash_income' => 0,
            'non_cash_expense' => 0,
        ];

        DB::table('order_transactions')
            ->select(['id', 'paid_at', 'channel_breakdown'])
            ->where('amount', '>', 0)
            ->orderBy('id')
            ->each(function (object $transaction) use (&$daily, $emptyDay): void {
                $date = Str::substr((string) $transaction->paid_at, 0, 10);
                $channels = is_string($transaction->channel_breakdown)
                    ? json_decode($transaction->channel_breakdown, true, flags: JSON_THROW_ON_ERROR)
                    : $transaction->channel_breakdown;

                foreach ($channels as $channel) {
                    $daily[$date] ??= $emptyDay();
                    $column = Str::before((string) ($channel['label'] ?? ''), ' · ') === 'Tunai'
                        ? 'cash_income'
                        : 'non_cash_income';
                    $daily[$date][$column] += (int) ($channel['amount'] ?? 0);
                }
            });

        DB::table('cash_entries')
            ->select(['id', 'entry_date', 'direction', 'amount', 'method'])
            ->orderBy('id')
            ->each(function (object $entry) use (&$daily, $emptyDay): void {
                $date = Str::substr((string) $entry->entry_date, 0, 10);
                $daily[$date] ??= $emptyDay();
                $prefix = $entry->method === 'Tunai' ? 'cash' : 'non_cash';
                $suffix = $entry->direction === 'out' ? 'expense' : 'income';
                $daily[$date]["{$prefix}_{$suffix}"] += (int) $entry->amount;
            });

        ksort($daily);

        $cashBalance = 0;
        $nonCashBalance = 0;
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

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('daily_balance')->insert($chunk);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('daily_balance')->delete();
    }
};
