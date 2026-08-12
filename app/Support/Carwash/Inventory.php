<?php

namespace App\Support\Carwash;

/**
 * Operational stock and supplies (BR-09).
 */
class Inventory
{
    /**
     * @return list<array{id: int, sku: string, name: string, category: string, unit: string, quantity: int, minQuantity: int, unitCost: int, supplier: string, updatedAt: string}>
     */
    public static function items(): array
    {
        return [
            ['id' => 1, 'sku' => 'SNW-001', 'name' => 'Snow Foam pH Netral', 'category' => 'Bahan Cuci', 'unit' => 'galon', 'quantity' => 3, 'minQuantity' => 6, 'unitCost' => 320000, 'supplier' => 'PT Kilau Kimia', 'updatedAt' => '3 Agu 2026'],
            ['id' => 2, 'sku' => 'SHP-002', 'name' => 'Shampoo Mobil Konsentrat', 'category' => 'Bahan Cuci', 'unit' => 'liter', 'quantity' => 24, 'minQuantity' => 10, 'unitCost' => 85000, 'supplier' => 'PT Kilau Kimia', 'updatedAt' => '3 Agu 2026'],
            ['id' => 3, 'sku' => 'WAX-003', 'name' => 'Carnauba Wax Premium', 'category' => 'Detailing', 'unit' => 'botol', 'quantity' => 8, 'minQuantity' => 5, 'unitCost' => 175000, 'supplier' => 'Detail Pro ID', 'updatedAt' => '2 Agu 2026'],
            ['id' => 4, 'sku' => 'CTG-004', 'name' => 'Nano Ceramic Coating 9H', 'category' => 'Detailing', 'unit' => 'set', 'quantity' => 4, 'minQuantity' => 3, 'unitCost' => 850000, 'supplier' => 'Detail Pro ID', 'updatedAt' => '2 Agu 2026'],
            ['id' => 5, 'sku' => 'MFT-005', 'name' => 'Microfiber Towel 40x40', 'category' => 'Perlengkapan', 'unit' => 'pcs', 'quantity' => 96, 'minQuantity' => 40, 'unitCost' => 15000, 'supplier' => 'Toko Bersih Jaya', 'updatedAt' => '1 Agu 2026'],
            ['id' => 6, 'sku' => 'TRE-006', 'name' => 'Semir Ban Gloss', 'category' => 'Add-on', 'unit' => 'liter', 'quantity' => 2, 'minQuantity' => 4, 'unitCost' => 95000, 'supplier' => 'PT Kilau Kimia', 'updatedAt' => '3 Agu 2026'],
            ['id' => 7, 'sku' => 'PRF-007', 'name' => 'Parfum Kabin Aroma Citrus', 'category' => 'Add-on', 'unit' => 'botol', 'quantity' => 34, 'minQuantity' => 12, 'unitCost' => 28000, 'supplier' => 'Aroma Nusantara', 'updatedAt' => '3 Agu 2026'],
            ['id' => 8, 'sku' => 'GLS-008', 'name' => 'Cairan Anti Jamur Kaca', 'category' => 'Add-on', 'unit' => 'botol', 'quantity' => 11, 'minQuantity' => 8, 'unitCost' => 42000, 'supplier' => 'PT Kilau Kimia', 'updatedAt' => '2 Agu 2026'],
            ['id' => 9, 'sku' => 'INT-009', 'name' => 'Interior Cleaner Foam', 'category' => 'Interior', 'unit' => 'botol', 'quantity' => 15, 'minQuantity' => 8, 'unitCost' => 68000, 'supplier' => 'Detail Pro ID', 'updatedAt' => '1 Agu 2026'],
            ['id' => 10, 'sku' => 'SRG-010', 'name' => 'Sarung Tangan Karet', 'category' => 'Perlengkapan', 'unit' => 'pasang', 'quantity' => 5, 'minQuantity' => 12, 'unitCost' => 18000, 'supplier' => 'Toko Bersih Jaya', 'updatedAt' => '31 Jul 2026'],
            ['id' => 11, 'sku' => 'SPN-011', 'name' => 'Spons Cuci Halus', 'category' => 'Perlengkapan', 'unit' => 'pcs', 'quantity' => 48, 'minQuantity' => 20, 'unitCost' => 12000, 'supplier' => 'Toko Bersih Jaya', 'updatedAt' => '30 Jul 2026'],
            ['id' => 12, 'sku' => 'OLI-012', 'name' => 'Degreaser Ruang Mesin', 'category' => 'Detailing', 'unit' => 'liter', 'quantity' => 7, 'minQuantity' => 5, 'unitCost' => 78000, 'supplier' => 'PT Kilau Kimia', 'updatedAt' => '29 Jul 2026'],
        ];
    }

    /**
     * @return list<array{id: int, itemId: int, item: string, sku: string, type: string, quantity: int, note: string, date: string, time: string, by: string}>
     */
    public static function movements(): array
    {
        return [
            ['id' => 1, 'itemId' => 1, 'item' => 'Snow Foam pH Netral', 'sku' => 'SNW-001', 'type' => 'keluar', 'quantity' => 2, 'note' => 'Pemakaian shift pagi Bay 1-3', 'date' => '3 Agu 2026', 'time' => '11.10', 'by' => 'Agus Setiawan'],
            ['id' => 2, 'itemId' => 7, 'item' => 'Parfum Kabin Aroma Citrus', 'sku' => 'PRF-007', 'type' => 'masuk', 'quantity' => 24, 'note' => 'Restock dari Aroma Nusantara', 'date' => '3 Agu 2026', 'time' => '09.30', 'by' => 'Yuni Astuti'],
            ['id' => 3, 'itemId' => 6, 'item' => 'Semir Ban Gloss', 'sku' => 'TRE-006', 'type' => 'keluar', 'quantity' => 1, 'note' => 'Pemakaian layanan add-on', 'date' => '3 Agu 2026', 'time' => '08.50', 'by' => 'Bagas Pratomo'],
            ['id' => 4, 'itemId' => 5, 'item' => 'Microfiber Towel 40x40', 'sku' => 'MFT-005', 'type' => 'masuk', 'quantity' => 36, 'note' => 'Pembelian 3 lusin', 'date' => '1 Agu 2026', 'time' => '16.05', 'by' => 'Yuni Astuti'],
            ['id' => 5, 'itemId' => 5, 'item' => 'Microfiber Towel 40x40', 'sku' => 'MFT-005', 'type' => 'penyesuaian', 'quantity' => -4, 'note' => 'Rusak / sobek saat pemakaian', 'date' => '1 Agu 2026', 'time' => '18.20', 'by' => 'Sinta Dewi'],
            ['id' => 6, 'itemId' => 2, 'item' => 'Shampoo Mobil Konsentrat', 'sku' => 'SHP-002', 'type' => 'keluar', 'quantity' => 6, 'note' => 'Pemakaian mingguan', 'date' => '31 Jul 2026', 'time' => '19.00', 'by' => 'Agus Setiawan'],
            ['id' => 7, 'itemId' => 4, 'item' => 'Nano Ceramic Coating 9H', 'sku' => 'CTG-004', 'type' => 'keluar', 'quantity' => 1, 'note' => 'Job coating B 5150 AB', 'date' => '3 Agu 2026', 'time' => '09.20', 'by' => 'Tim Detailing'],
            ['id' => 8, 'itemId' => 10, 'item' => 'Sarung Tangan Karet', 'sku' => 'SRG-010', 'type' => 'keluar', 'quantity' => 8, 'note' => 'Distribusi ke crew', 'date' => '31 Jul 2026', 'time' => '07.45', 'by' => 'Sinta Dewi'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return array_values(array_unique(array_column(self::items(), 'category')));
    }

    /**
     * @return list<string>
     */
    public static function movementTypes(): array
    {
        return ['masuk', 'keluar', 'penyesuaian'];
    }
}
