<?php

namespace App\Support\Admin;

use App\Models\Member;
use Carbon\CarbonImmutable;

/**
 * The four headline cards on the live dashboard (BR-12).
 *
 * Every figure is read from the module that owns it — takings from the finance
 * ledger, vehicles from the order board, members from the member table — so the
 * dashboard can only ever restate what those pages already show.
 */
class DashboardStats
{
    /**
     * @param  list<array<string, mixed>>  $moneyIn
     * @return list<array{label: string, value: string, caption: string, delta: float|null, trend: string, icon: string}>
     */
    public static function forDate(string $date, array $moneyIn): array
    {
        $previousDate = CarbonImmutable::createFromFormat('!Y-m-d', $date)->subDay()->toDateString();
        $previousMoneyIn = FinanceQueries::ledgerForDate($previousDate)['moneyIn'];
        $orderSummary = OrderQueries::summaryForDate($date);
        $previousOrderSummary = OrderQueries::summaryForDate($previousDate);

        $revenue = (int) array_sum(array_column($moneyIn, 'amount'));
        $previousRevenue = (int) array_sum(array_column($previousMoneyIn, 'amount'));
        $activeMembers = Member::query()->where('is_active', true)->count();
        $totalMembers = Member::query()->count();
        $isToday = $date === CarbonImmutable::now()->toDateString();

        return [
            [
                'label' => $isToday ? 'Pendapatan Hari Ini' : 'Pendapatan',
                'value' => 'Rp '.self::number($revenue),
                'caption' => 'dari '.self::number(count($moneyIn)).' transaksi keuangan',
                ...self::comparison($revenue, $previousRevenue),
                'icon' => 'wallet',
            ],
            [
                'label' => 'Kendaraan Dilayani',
                'value' => self::number($orderSummary['served']),
                'caption' => 'dari '.self::number($orderSummary['total']).' order kendaraan',
                ...self::comparison($orderSummary['served'], $previousOrderSummary['served']),
                'icon' => 'car',
            ],
            [
                'label' => 'Member Aktif',
                'value' => self::number($activeMembers),
                'caption' => 'dari '.self::number($totalMembers).' member terdaftar',
                /* The member base is a standing total, not a daily flow. */
                ...self::comparison($activeMembers, $activeMembers),
                'icon' => 'users',
            ],
            [
                'label' => 'Stempel Ditukar',
                'value' => '0',
                /* Loyalty has no live source yet: the reward module is demo-only. */
                'caption' => 'Menunggu modul reward',
                'delta' => 0.0,
                'trend' => 'flat',
                'icon' => 'gift',
            ],
        ];
    }

    /**
     * Movement against the day before. A day that follows an empty one has no
     * percentage to report, so the card is left without a delta rather than
     * claiming an infinite rise.
     *
     * @return array{delta: float|null, trend: string}
     */
    private static function comparison(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'delta' => $current === 0 ? 0.0 : null,
                'trend' => 'flat',
            ];
        }

        $delta = round((($current - $previous) / $previous) * 100, 1);

        return [
            'delta' => $delta,
            'trend' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
        ];
    }

    private static function number(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
