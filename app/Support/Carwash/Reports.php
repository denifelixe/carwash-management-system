<?php

namespace App\Support\Carwash;

/**
 * Aggregated figures for the dashboard (BR-12) and reports module.
 */
class Reports
{
    /**
     * @return list<array{label: string, value: string, caption: string, delta: float, trend: string, icon: string}>
     */
    public static function todayStats(): array
    {
        return [
            ['label' => 'Pendapatan Hari Ini', 'value' => 'Rp 4.850.000', 'caption' => 'dari 38 transaksi', 'delta' => 12.4, 'trend' => 'up', 'icon' => 'wallet'],
            ['label' => 'Kendaraan Dilayani', 'value' => '38', 'caption' => '6 unit masih antre', 'delta' => 8.6, 'trend' => 'up', 'icon' => 'car'],
            ['label' => 'Customer Aktif', 'value' => '1.284', 'caption' => '+24 member bulan ini', 'delta' => 3.1, 'trend' => 'up', 'icon' => 'users'],
            ['label' => 'Stempel Ditukar', 'value' => '142', 'caption' => '19 reward diklaim', 'delta' => -2.8, 'trend' => 'down', 'icon' => 'gift'],
        ];
    }

    /**
     * @return list<array{day: string, date: string, revenue: int, transactions: int}>
     */
    public static function revenueTrend(): array
    {
        return [
            ['day' => 'Sen', 'date' => '28 Jul', 'revenue' => 3250000, 'transactions' => 27],
            ['day' => 'Sel', 'date' => '29 Jul', 'revenue' => 2980000, 'transactions' => 24],
            ['day' => 'Rab', 'date' => '30 Jul', 'revenue' => 3760000, 'transactions' => 31],
            ['day' => 'Kam', 'date' => '31 Jul', 'revenue' => 4120000, 'transactions' => 33],
            ['day' => 'Jum', 'date' => '1 Agu', 'revenue' => 5240000, 'transactions' => 41],
            ['day' => 'Sab', 'date' => '2 Agu', 'revenue' => 6480000, 'transactions' => 52],
            ['day' => 'Min', 'date' => '3 Agu', 'revenue' => 4850000, 'transactions' => 38],
        ];
    }

    /**
     * @return list<array{name: string, orders: int, revenue: int}>
     */
    public static function topServices(): array
    {
        return [
            ['name' => 'Cuci Mobil Reguler', 'orders' => 412, 'revenue' => 18540000],
            ['name' => 'Snow Wash Premium', 'orders' => 236, 'revenue' => 28320000],
            ['name' => 'Cuci Mobil + Wax', 'orders' => 198, 'revenue' => 16830000],
            ['name' => 'Deep Clean Interior', 'orders' => 124, 'revenue' => 18600000],
            ['name' => 'Cuci Motor Reguler', 'orders' => 96, 'revenue' => 1920000],
        ];
    }

    /**
     * Customer activity rollup for the reports module.
     *
     * @return array{newCustomers: int, returningCustomers: int, churnRisk: int, stampsIssued: int, stampsRedeemed: int, rewardsClaimed: int, averageVisitsPerCustomer: float}
     */
    public static function customerActivity(): array
    {
        return [
            'newCustomers' => 24,
            'returningCustomers' => 186,
            'churnRisk' => 12,
            'stampsIssued' => 418,
            'stampsRedeemed' => 142,
            'rewardsClaimed' => 19,
            'averageVisitsPerCustomer' => 3.4,
        ];
    }

    /**
     * @return array{total: int, scheduled: int, completed: int, cancelled: int, showRate: float, peakSlot: string}
     */
    public static function bookingSummary(): array
    {
        return [
            'total' => 64,
            'scheduled' => 21,
            'completed' => 38,
            'cancelled' => 5,
            'showRate' => 92.2,
            'peakSlot' => '09.00 - 11.00',
        ];
    }

    /**
     * @return array{totalItems: int, lowStock: int, stockValue: int, movementsThisWeek: int, topConsumed: string}
     */
    public static function inventorySummary(): array
    {
        return [
            'totalItems' => 12,
            'lowStock' => 3,
            'stockValue' => 14_286_000,
            'movementsThisWeek' => 28,
            'topConsumed' => 'Snow Foam pH Netral',
        ];
    }

    /**
     * Monthly revenue vs expense used by the reports charts.
     *
     * @return list<array{month: string, revenue: int, expense: int}>
     */
    public static function monthlyTrend(): array
    {
        return [
            ['month' => 'Mar', 'revenue' => 96400000, 'expense' => 41200000],
            ['month' => 'Apr', 'revenue' => 104800000, 'expense' => 44600000],
            ['month' => 'Mei', 'revenue' => 112300000, 'expense' => 46900000],
            ['month' => 'Jun', 'revenue' => 108600000, 'expense' => 45100000],
            ['month' => 'Jul', 'revenue' => 126900000, 'expense' => 49800000],
            ['month' => 'Agu', 'revenue' => 30680000, 'expense' => 12400000],
        ];
    }
}
