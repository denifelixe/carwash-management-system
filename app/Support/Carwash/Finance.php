<?php

namespace App\Support\Carwash;

use Illuminate\Support\Str;

/**
 * Cash flow management (BR-10): money in, money out, and their categories.
 */
class Finance
{
    /**
     * @return list<array{id: string, ref: string, date: string, time: string, category: string, description: string, amount: int, method: string, channelBreakdown: list<array{label: string, amount: int}>, recordedBy: string, source: string, orderId: int|null, orderNo: string|null, customer: string|null, vehicle: string|null, plate: string|null}>
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
     * @return list<array{id: string, ref: string, date: string, time: string, category: string, description: string, amount: int, method: string, channelBreakdown: list<array{label: string, amount: int}>, recordedBy: string, source: string, orderId: int, orderNo: string, customer: string, vehicle: string, plate: string}>
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
                    : 'Pembayaran Lunas/Sisa Order';

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
                    'recordedBy' => $transaction['time'] >= '15.00'
                        ? 'Rina Marlina'
                        : 'Yuni Astuti',
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
     * @return list<array{id: string, ref: string, date: string, time: string, category: string, description: string, amount: int, method: string, channelBreakdown: list<array{label: string, amount: int}>, recordedBy: string, source: string, orderId: null, orderNo: null, customer: null, vehicle: null, plate: null}>
     */
    private static function manualMoneyIn(): array
    {
        return [
            ['id' => 'manual-income-29', 'ref' => self::transactionRef('Penjualan Produk', self::date(0), 29), 'date' => self::date(0), 'time' => '09.05', 'category' => 'Penjualan Produk', 'description' => 'Penjualan parfum mobil 6 botol', 'amount' => 360000, 'method' => 'Tunai', 'channelBreakdown' => [['label' => 'Tunai', 'amount' => 360000]], 'recordedBy' => 'Yuni Astuti', 'source' => 'manual', 'orderId' => null, 'orderNo' => null, 'customer' => null, 'vehicle' => null, 'plate' => null],
            ['id' => 'manual-income-27', 'ref' => self::transactionRef('Sewa Tempat', self::date(1), 27), 'date' => self::date(1), 'time' => '15.10', 'category' => 'Sewa Tempat', 'description' => 'Sewa lapak kopi area tunggu', 'amount' => 1500000, 'method' => 'Transfer', 'channelBreakdown' => [['label' => 'Transfer', 'amount' => 1500000]], 'recordedBy' => 'Sinta Dewi', 'source' => 'manual', 'orderId' => null, 'orderNo' => null, 'customer' => null, 'vehicle' => null, 'plate' => null],
            ['id' => 'manual-income-26', 'ref' => self::transactionRef('Pendapatan Lain', self::date(2), 26), 'date' => self::date(2), 'time' => '19.55', 'category' => 'Pendapatan Lain', 'description' => 'Penjualan limbah kemasan operasional', 'amount' => 250000, 'method' => 'Tunai', 'channelBreakdown' => [['label' => 'Tunai', 'amount' => 250000]], 'recordedBy' => 'Rina Marlina', 'source' => 'manual', 'orderId' => null, 'orderNo' => null, 'customer' => null, 'vehicle' => null, 'plate' => null],
        ];
    }

    /**
     * Operational expenses. Attachments are mandatory for outgoing money (BR-10).
     *
     * @return list<array{id: int, ref: string, date: string, time: string, category: string, description: string, amount: int, method: string, channelBreakdown: list<array{label: string, amount: int}>, recordedBy: string, attachment: array{name: string, size: string}|null}>
     */
    public static function moneyOut(): array
    {
        return [
            ['id' => 1, 'ref' => self::transactionRef('Pembelian Bahan', self::date(0), 22), 'date' => self::date(0), 'time' => '10.35', 'category' => 'Pembelian Bahan', 'description' => 'Snow foam 4 galon + shampoo pH netral', 'amount' => 1280000, 'method' => 'Transfer', 'channelBreakdown' => [['label' => 'Transfer', 'amount' => 1280000]], 'recordedBy' => 'Yuni Astuti', 'attachment' => ['name' => 'nota-supplier-0803.jpg', 'size' => '412 KB']],
            ['id' => 2, 'ref' => self::transactionRef('Operasional', self::date(0), 21), 'date' => self::date(0), 'time' => '09.15', 'category' => 'Operasional', 'description' => 'Token listrik bulanan', 'amount' => 500000, 'method' => 'QRIS', 'channelBreakdown' => [['label' => 'QRIS', 'amount' => 500000]], 'recordedBy' => 'Yuni Astuti', 'attachment' => ['name' => 'struk-token-listrik.pdf', 'size' => '128 KB']],
            ['id' => 3, 'ref' => self::transactionRef('Gaji & Upah', self::date(1), 20), 'date' => self::date(1), 'time' => '17.40', 'category' => 'Gaji & Upah', 'description' => 'Uang makan crew shift sore (5 orang)', 'amount' => 175000, 'method' => 'Tunai', 'channelBreakdown' => [['label' => 'Tunai', 'amount' => 175000]], 'recordedBy' => 'Rina Marlina', 'attachment' => ['name' => 'rekap-uang-makan.jpg', 'size' => '287 KB']],
            ['id' => 4, 'ref' => self::transactionRef('Perawatan Alat', self::date(1), 19), 'date' => self::date(1), 'time' => '11.20', 'category' => 'Perawatan Alat', 'description' => 'Servis mesin high pressure Bay 2', 'amount' => 850000, 'method' => 'Tunai', 'channelBreakdown' => [['label' => 'Tunai', 'amount' => 850000]], 'recordedBy' => 'Sinta Dewi', 'attachment' => ['name' => 'invoice-servis-mesin.pdf', 'size' => '96 KB']],
            ['id' => 5, 'ref' => self::transactionRef('Pembelian Bahan', self::date(2), 18), 'date' => self::date(2), 'time' => '16.05', 'category' => 'Pembelian Bahan', 'description' => 'Microfiber towel 3 lusin', 'amount' => 540000, 'method' => 'Transfer', 'channelBreakdown' => [['label' => 'Transfer', 'amount' => 540000]], 'recordedBy' => 'Yuni Astuti', 'attachment' => ['name' => 'nota-microfiber.jpg', 'size' => '355 KB']],
            ['id' => 6, 'ref' => self::transactionRef('Marketing', self::date(2), 17), 'date' => self::date(2), 'time' => '08.30', 'category' => 'Marketing', 'description' => 'Iklan Instagram promo Senin Kinclong', 'amount' => 300000, 'method' => 'Debit', 'channelBreakdown' => [['label' => 'Debit', 'amount' => 300000]], 'recordedBy' => 'Sinta Dewi', 'attachment' => ['name' => 'bukti-bayar-ads.png', 'size' => '204 KB']],
        ];
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
        $categoryWords = preg_split(
            '/[^A-Z0-9]+/',
            Str::upper($category),
            flags: PREG_SPLIT_NO_EMPTY,
        );
        $categoryCode = implode('', array_map(
            fn (string $word): string => Str::substr($word, 0, 1),
            $categoryWords ?: [],
        ));
        $dateCode = Str::of($date)->remove('-')->substr(2);
        $stableIdentifier = is_int($identifier)
            ? str_pad((string) $identifier, 4, '0', STR_PAD_LEFT)
            : Str::of($identifier)->upper()->replaceMatches('/[^A-Z0-9]+/', '');

        return "TRX-{$categoryCode}-{$dateCode}-{$stableIdentifier}";
    }

    /**
     * @return list<string>
     */
    public static function incomeCategories(): array
    {
        return ['Pembayaran Lunas/Sisa Order', 'Pembayaran Sebagian/Booking Order', 'Penjualan Produk', 'Sewa Tempat', 'Pendapatan Lain'];
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
     * @return array{openingBalance: int, todayIn: int, todayOut: int, closingBalance: int, pendingPayments: int}
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
        $servedOrders = Operations::servedOrders($date);

        return array_map(function (array $shift) use ($income, $expenses, $servedOrders): array {
            $isMorning = $shift['id'] === 'pagi';
            $shiftIncome = array_values(array_filter(
                $income,
                fn (array $entry): bool => ($entry['time'] < '15.00') === $isMorning,
            ));
            $shiftExpenses = array_values(array_filter(
                $expenses,
                fn (array $entry): bool => ($entry['time'] < '15.00') === $isMorning,
            ));
            $posIncome = array_values(array_filter(
                $shiftIncome,
                fn (array $entry): bool => $entry['source'] === 'pos',
            ));
            $shiftOrders = array_values(array_filter(
                $servedOrders,
                fn (array $order): bool => ($order['time'] < '15.00') === $isMorning,
            ));

            return [
                ...$shift,
                'revenue' => array_sum(array_column($posIncome, 'amount')),
                'vehiclesServed' => count($shiftOrders),
                'moneyIn' => array_sum(array_column($shiftIncome, 'amount')),
                'moneyOut' => array_sum(array_column($shiftExpenses, 'amount')),
            ];
        }, Brand::shifts());
    }
}
