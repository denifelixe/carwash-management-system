<?php

namespace App\Support\Carwash;

/**
 * Cash flow management (BR-10): money in, money out, and their categories.
 */
class Finance
{
    /**
     * @return list<array{id: int, ref: string, date: string, time: string, category: string, description: string, amount: int, method: string, recordedBy: string, source: string}>
     */
    public static function moneyIn(): array
    {
        return [
            ['id' => 1, 'ref' => 'IN-2608-0031', 'date' => '3 Agu 2026', 'time' => '11.02', 'category' => 'Penjualan Layanan', 'description' => 'Setoran POS shift pagi', 'amount' => 2940000, 'method' => 'QRIS', 'recordedBy' => 'Yuni Astuti', 'source' => 'pos'],
            ['id' => 2, 'ref' => 'IN-2608-0030', 'date' => '3 Agu 2026', 'time' => '10.20', 'category' => 'DP Booking', 'description' => 'DP 50% nano coating B 5150 AB', 'amount' => 750000, 'method' => 'Transfer', 'recordedBy' => 'Yuni Astuti', 'source' => 'manual'],
            ['id' => 3, 'ref' => 'IN-2608-0029', 'date' => '3 Agu 2026', 'time' => '09.05', 'category' => 'Penjualan Produk', 'description' => 'Penjualan parfum mobil 6 botol', 'amount' => 360000, 'method' => 'Tunai', 'recordedBy' => 'Yuni Astuti', 'source' => 'manual'],
            ['id' => 4, 'ref' => 'IN-2608-0028', 'date' => '2 Agu 2026', 'time' => '20.40', 'category' => 'Penjualan Layanan', 'description' => 'Setoran POS shift sore', 'amount' => 3180000, 'method' => 'Tunai', 'recordedBy' => 'Rina Marlina', 'source' => 'pos'],
            ['id' => 5, 'ref' => 'IN-2608-0027', 'date' => '2 Agu 2026', 'time' => '15.10', 'category' => 'Sewa Tempat', 'description' => 'Sewa lapak kopi area tunggu', 'amount' => 1500000, 'method' => 'Transfer', 'recordedBy' => 'Sinta Dewi', 'source' => 'manual'],
            ['id' => 6, 'ref' => 'IN-2608-0026', 'date' => '1 Agu 2026', 'time' => '19.55', 'category' => 'Penjualan Layanan', 'description' => 'Setoran POS shift sore', 'amount' => 2740000, 'method' => 'QRIS', 'recordedBy' => 'Rina Marlina', 'source' => 'pos'],
        ];
    }

    /**
     * Operational expenses. Attachments are mandatory for outgoing money (BR-10).
     *
     * @return list<array{id: int, ref: string, date: string, time: string, category: string, description: string, amount: int, method: string, recordedBy: string, attachment: array{name: string, size: string}|null}>
     */
    public static function moneyOut(): array
    {
        return [
            ['id' => 1, 'ref' => 'OUT-2608-0022', 'date' => '3 Agu 2026', 'time' => '10.35', 'category' => 'Pembelian Bahan', 'description' => 'Snow foam 4 galon + shampoo pH netral', 'amount' => 1280000, 'method' => 'Transfer', 'recordedBy' => 'Yuni Astuti', 'attachment' => ['name' => 'nota-supplier-0803.jpg', 'size' => '412 KB']],
            ['id' => 2, 'ref' => 'OUT-2608-0021', 'date' => '3 Agu 2026', 'time' => '09.15', 'category' => 'Operasional', 'description' => 'Token listrik bulanan', 'amount' => 500000, 'method' => 'QRIS', 'recordedBy' => 'Yuni Astuti', 'attachment' => ['name' => 'struk-token-listrik.pdf', 'size' => '128 KB']],
            ['id' => 3, 'ref' => 'OUT-2608-0020', 'date' => '2 Agu 2026', 'time' => '17.40', 'category' => 'Gaji & Upah', 'description' => 'Uang makan crew shift sore (5 orang)', 'amount' => 175000, 'method' => 'Tunai', 'recordedBy' => 'Rina Marlina', 'attachment' => ['name' => 'rekap-uang-makan.jpg', 'size' => '287 KB']],
            ['id' => 4, 'ref' => 'OUT-2608-0019', 'date' => '2 Agu 2026', 'time' => '11.20', 'category' => 'Perawatan Alat', 'description' => 'Servis mesin high pressure Bay 2', 'amount' => 850000, 'method' => 'Tunai', 'recordedBy' => 'Sinta Dewi', 'attachment' => ['name' => 'invoice-servis-mesin.pdf', 'size' => '96 KB']],
            ['id' => 5, 'ref' => 'OUT-2608-0018', 'date' => '1 Agu 2026', 'time' => '16.05', 'category' => 'Pembelian Bahan', 'description' => 'Microfiber towel 3 lusin', 'amount' => 540000, 'method' => 'Transfer', 'recordedBy' => 'Yuni Astuti', 'attachment' => ['name' => 'nota-microfiber.jpg', 'size' => '355 KB']],
            ['id' => 6, 'ref' => 'OUT-2608-0017', 'date' => '1 Agu 2026', 'time' => '08.30', 'category' => 'Marketing', 'description' => 'Iklan Instagram promo Senin Kinclong', 'amount' => 300000, 'method' => 'Debit', 'recordedBy' => 'Sinta Dewi', 'attachment' => ['name' => 'bukti-bayar-ads.png', 'size' => '204 KB']],
        ];
    }

    /**
     * @return list<string>
     */
    public static function incomeCategories(): array
    {
        return ['Penjualan Layanan', 'Penjualan Produk', 'DP Booking', 'Sewa Tempat', 'Pendapatan Lain'];
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
    public static function summary(): array
    {
        $todayIn = 4050000;
        $todayOut = 1780000;

        return [
            'openingBalance' => 12400000,
            'todayIn' => $todayIn,
            'todayOut' => $todayOut,
            'closingBalance' => 12400000 + $todayIn - $todayOut,
            'pendingPayments' => 130000,
        ];
    }
}
