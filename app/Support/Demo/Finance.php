<?php

namespace App\Support\Demo;

use App\Support\Admin\FinanceReference;

/**
 * Cash flow management (BR-10): money in, money out, and their categories.
 */
class Finance
{
    /**
     * @return list<array{id: string, ref: string, date: string, time: string, category: string, description: string, amount: int, method: string, channelBreakdown: list<array{label: string, amount: int}>, recordedBy: string, shift: string|null, source: string, orderId: int|null, orderNo: string|null, customer: string|null, vehicle: string|null, plate: string|null}>
     */
    public static function moneyIn(): array
    {
        $entries = self::posMoneyIn();

        array_push($entries, ...self::manualMoneyIn());

        usort(
            $entries,
            fn (array $first, array $second): int => [$second['date'], $second['time'], $second['ref']]
                <=> [$first['date'], $first['time'], $first['ref']],
        );

        return $entries;
    }

    /**
     * Every payment accepted by POS is one cash entry on the date it occurred,
     * regardless of the order's operational status.
     *
     * @return list<array{id: string, ref: string, date: string, time: string, category: string, description: string, amount: int, method: string, channelBreakdown: list<array{label: string, amount: int}>, recordedBy: string, shift: string|null, source: string, orderId: int, orderNo: string, customer: string, vehicle: string, plate: string}>
     */
    private static function posMoneyIn(): array
    {
        $entries = [];

        foreach (Operations::orders() as $order) {
            foreach ($order['transactions'] as $transactionIndex => $transaction) {
                if ($transaction['amount'] <= 0) {
                    continue;
                }

                $category = $transaction['type'] === 'Pembayaran Sebagian'
                    ? 'Pembayaran Sebagian/Booking Order'
                    : 'Pembayaran Sisa/Lunas (Order Selesai)';

                $entries[] = [
                    'id' => 'pos-'.$transaction['id'],
                    'ref' => self::transactionRef(
                        $transaction['type'].' Order',
                        $transaction['date'],
                        $order['orderNo'].'-TRX-'.($transactionIndex + 1),
                    ),
                    'date' => $transaction['date'],
                    'time' => $transaction['time'],
                    'category' => $category,
                    'description' => $order['items'],
                    'amount' => $transaction['amount'],
                    'method' => $transaction['channels'],
                    'channelBreakdown' => $transaction['channelBreakdown'],
                    'recordedBy' => self::cashierOf($transaction['shift']),
                    'shift' => $transaction['shift'],
                    'source' => 'pos',
                    'orderId' => $order['id'],
                    'orderNo' => $order['orderNo'],
                    'customer' => $order['customer'],
                    'vehicle' => $order['vehicle'],
                    'plate' => $order['plate'],
                ];
            }
        }

        return $entries;
    }

    /**
     * @return list<array{id: string, ref: string, date: string, time: string, category: string, description: string, amount: int, method: string, channelBreakdown: list<array{label: string, amount: int}>, recordedBy: string, shift: string|null, source: string, orderId: null, orderNo: null, customer: null, vehicle: null, plate: null}>
     */
    private static function manualMoneyIn(): array
    {
        return [
            ['id' => 'manual-income-29', 'ref' => self::transactionRef('Penjualan Produk', self::date(0), 29), 'date' => self::date(0), 'time' => '09.05', 'category' => 'Penjualan Produk', 'description' => 'Penjualan parfum mobil 6 botol', 'amount' => 360000, 'method' => 'Tunai', 'channelBreakdown' => [['label' => 'Tunai', 'amount' => 360000]], 'shift' => self::shiftOf('Yuni Astuti'), 'recordedBy' => 'Yuni Astuti', 'source' => 'manual', 'orderId' => null, 'orderNo' => null, 'customer' => null, 'vehicle' => null, 'plate' => null],
            ['id' => 'manual-income-27', 'ref' => self::transactionRef('Sewa Tempat', self::date(1), 27), 'date' => self::date(1), 'time' => '15.10', 'category' => 'Sewa Tempat', 'description' => 'Sewa lapak kopi area tunggu', 'amount' => 1500000, 'method' => 'Transfer', 'channelBreakdown' => [['label' => 'Transfer', 'amount' => 1500000]], 'shift' => self::shiftOf('Sinta Dewi'), 'recordedBy' => 'Sinta Dewi', 'source' => 'manual', 'orderId' => null, 'orderNo' => null, 'customer' => null, 'vehicle' => null, 'plate' => null],
            ['id' => 'manual-income-26', 'ref' => self::transactionRef('Pendapatan Lain', self::date(2), 26), 'date' => self::date(2), 'time' => '19.55', 'category' => 'Pendapatan Lain', 'description' => 'Penjualan limbah kemasan operasional', 'amount' => 250000, 'method' => 'Tunai', 'channelBreakdown' => [['label' => 'Tunai', 'amount' => 250000]], 'shift' => self::shiftOf('Rina Marlina'), 'recordedBy' => 'Rina Marlina', 'source' => 'manual', 'orderId' => null, 'orderNo' => null, 'customer' => null, 'vehicle' => null, 'plate' => null],
        ];
    }

    /**
     * Operational expenses. Attachments are mandatory for outgoing money (BR-10).
     *
     * @return list<array{id: int, ref: string, date: string, time: string, category: string, description: string, amount: int, method: string, channelBreakdown: list<array{label: string, amount: int}>, recordedBy: string, shift: string|null, attachment: array{name: string, size: string}|null}>
     */
    public static function moneyOut(): array
    {
        return [
            ['id' => 1, 'ref' => self::transactionRef('Pembelian Bahan', self::date(0), 22), 'date' => self::date(0), 'time' => '10.35', 'category' => 'Pembelian Bahan', 'description' => 'Snow foam 4 galon + shampoo pH netral', 'amount' => 1280000, 'method' => 'Transfer', 'channelBreakdown' => [['label' => 'Transfer', 'amount' => 1280000]], 'shift' => self::shiftOf('Yuni Astuti'), 'recordedBy' => 'Yuni Astuti', 'attachment' => ['name' => 'nota-supplier-0803.jpg', 'size' => '412 KB']],
            ['id' => 2, 'ref' => self::transactionRef('Operasional', self::date(0), 21), 'date' => self::date(0), 'time' => '09.15', 'category' => 'Operasional', 'description' => 'Token listrik bulanan', 'amount' => 500000, 'method' => 'QRIS', 'channelBreakdown' => [['label' => 'QRIS', 'amount' => 500000]], 'shift' => self::shiftOf('Yuni Astuti'), 'recordedBy' => 'Yuni Astuti', 'attachment' => ['name' => 'struk-token-listrik.pdf', 'size' => '128 KB']],
            ['id' => 3, 'ref' => self::transactionRef('Gaji & Upah', self::date(1), 20), 'date' => self::date(1), 'time' => '17.40', 'category' => 'Gaji & Upah', 'description' => 'Uang makan crew shift sore (5 orang)', 'amount' => 175000, 'method' => 'Tunai', 'channelBreakdown' => [['label' => 'Tunai', 'amount' => 175000]], 'shift' => self::shiftOf('Rina Marlina'), 'recordedBy' => 'Rina Marlina', 'attachment' => ['name' => 'rekap-uang-makan.jpg', 'size' => '287 KB']],
            ['id' => 4, 'ref' => self::transactionRef('Perawatan Alat', self::date(1), 19), 'date' => self::date(1), 'time' => '11.20', 'category' => 'Perawatan Alat', 'description' => 'Servis mesin high pressure Bay 2', 'amount' => 850000, 'method' => 'Tunai', 'channelBreakdown' => [['label' => 'Tunai', 'amount' => 850000]], 'shift' => self::shiftOf('Sinta Dewi'), 'recordedBy' => 'Sinta Dewi', 'attachment' => ['name' => 'invoice-servis-mesin.pdf', 'size' => '96 KB']],
            ['id' => 5, 'ref' => self::transactionRef('Pembelian Bahan', self::date(2), 18), 'date' => self::date(2), 'time' => '16.05', 'category' => 'Pembelian Bahan', 'description' => 'Microfiber towel 3 lusin', 'amount' => 540000, 'method' => 'Transfer', 'channelBreakdown' => [['label' => 'Transfer', 'amount' => 540000]], 'shift' => self::shiftOf('Yuni Astuti'), 'recordedBy' => 'Yuni Astuti', 'attachment' => ['name' => 'nota-microfiber.jpg', 'size' => '355 KB']],
            ['id' => 6, 'ref' => self::transactionRef('Marketing', self::date(2), 17), 'date' => self::date(2), 'time' => '08.30', 'category' => 'Marketing', 'description' => 'Iklan Instagram promo Senin Kinclong', 'amount' => 300000, 'method' => 'Debit', 'channelBreakdown' => [['label' => 'Debit', 'amount' => 300000]], 'shift' => self::shiftOf('Sinta Dewi'), 'recordedBy' => 'Sinta Dewi', 'attachment' => ['name' => 'bukti-bayar-ads.png', 'size' => '204 KB']],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private static function entriesOfShift(array $entries, string $shiftName): array
    {
        return array_values(array_filter(
            $entries,
            fn (array $entry): bool => $entry['shift'] === $shiftName,
        ));
    }

    /** Who was on the till for a shift; nobody, when the row carries none. */
    private static function cashierOf(?string $shiftName): string
    {
        foreach (Brand::shifts() as $shift) {
            if ($shift['name'] === $shiftName) {
                return $shift['cashier'];
            }
        }

        return '—';
    }

    /** The shift whoever wrote the row was rostered onto, as the live ledger reads it. */
    private static function shiftOf(string $recordedBy): ?string
    {
        foreach (RoleAccess::staff() as $person) {
            if ($person['name'] === $recordedBy) {
                return $person['shift'];
            }
        }

        return null;
    }

    /** A seeded day, counted back from today: 0 is today, 1 yesterday. */
    private static function date(int $daysBack): string
    {
        return Reports::today()->subDays($daysBack)->toDateString();
    }

    /** Transaction references use TRX-{category code}-{YYMMDD}-{stable ID}. */
    private static function transactionRef(
        string $category,
        string $date,
        string|int $identifier,
    ): string {
        return FinanceReference::make($category, $date, $identifier);
    }

    /**
     * @return list<string>
     */
    public static function incomeCategories(): array
    {
        return ['Pembayaran Sisa/Lunas (Order Selesai)', 'Pembayaran Sebagian/Booking Order', 'Penjualan Produk', 'Sewa Tempat', 'Pendapatan Lain'];
    }

    /**
     * @return list<string>
     */
    public static function expenseCategories(): array
    {
        return ['Pembelian Bahan', 'Gaji & Upah', 'Operasional', 'Perawatan Alat', 'Marketing', 'Sewa & Pajak', 'Pengeluaran Lain'];
    }

    /**
     * Rolling cash position used by the finance and dashboard summaries.
     *
     * @return array{openingBalance: int, todayIn: int, todayOut: int, remainingBalance: int, closingBalance: int, pendingPayments: int}
     */
    public static function summary(?string $date = null): array
    {
        $date ??= Reports::todayDate();
        $todayIn = array_sum(array_column(
            DateFilter::apply(self::moneyIn(), $date),
            'amount',
        ));
        $todayOut = array_sum(array_column(
            DateFilter::apply(self::moneyOut(), $date),
            'amount',
        ));

        return [
            'openingBalance' => 12400000,
            'todayIn' => $todayIn,
            'todayOut' => $todayOut,
            'remainingBalance' => $todayIn - $todayOut,
            'closingBalance' => 12400000 + $todayIn - $todayOut,
            'pendingPayments' => Operations::outstandingTotal(),
        ];
    }

    /**
     * Shift performance from the same POS payments and finance ledger entries
     * used by their respective modules.
     *
     * @return list<array{id: string, name: string, time: string, cashier: string, initials: string, revenue: int, transactions: int, vehiclesServed: int, moneyIn: int, moneyOut: int, status: string}>
     */
    public static function shiftSummary(string $date): array
    {
        $income = DateFilter::apply(self::moneyIn(), $date);
        $expenses = DateFilter::apply(self::moneyOut(), $date);

        return array_map(function (array $shift) use ($income, $expenses): array {
            $shiftIncome = self::entriesOfShift($income, $shift['name']);
            $shiftExpenses = self::entriesOfShift($expenses, $shift['name']);
            $posIncome = array_values(array_filter(
                $shiftIncome,
                fn (array $entry): bool => $entry['source'] === 'pos',
            ));

            return [
                ...$shift,
                'revenue' => array_sum(array_column($posIncome, 'amount')),
                'vehiclesServed' => count(array_unique(array_column($posIncome, 'orderId'))),
                'moneyIn' => array_sum(array_column($shiftIncome, 'amount')),
                'moneyOut' => array_sum(array_column($shiftExpenses, 'amount')),
            ];
        }, Brand::shifts());
    }
}
