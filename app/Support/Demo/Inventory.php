<?php

namespace App\Support\Demo;

use App\Support\Admin\Paginated;
use App\Support\Admin\StockQueries;
use Illuminate\Support\Str;

/**
 * Operational stock and supplies (BR-09).
 *
 * The fixtures are shaped exactly like App\Support\Admin\StockPresenter, since
 * the demo and the live module render one shared page.
 */
class Inventory
{
    /**
     * @return list<array{id: int, sku: string, name: string, category: string, unit: string, quantity: int, minQuantity: int, isLowStock: bool, unitCost: int, stockValue: int, supplier: string, notes: string, isActive: bool, updatedAt: string}>
     */
    public static function items(): array
    {
        return array_map(self::decorate(...), [
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
        ]);
    }

    /**
     * @return list<array{id: int, itemId: int, item: string, sku: string, unit: string, type: string, quantity: int, quantityAfter: int, note: string, date: string, time: string, by: string}>
     */
    public static function movements(): array
    {
        return [
            ['id' => 1, 'itemId' => 1, 'item' => 'Snow Foam pH Netral', 'sku' => 'SNW-001', 'unit' => 'galon', 'type' => 'keluar', 'quantity' => -2, 'quantityAfter' => 3, 'note' => 'Pemakaian shift pagi Bay 1-3', 'date' => '3 Agu 2026', 'time' => '11.10', 'by' => 'Agus Setiawan'],
            ['id' => 2, 'itemId' => 7, 'item' => 'Parfum Kabin Aroma Citrus', 'sku' => 'PRF-007', 'unit' => 'botol', 'type' => 'masuk', 'quantity' => 24, 'quantityAfter' => 34, 'note' => 'Restock dari Aroma Nusantara', 'date' => '3 Agu 2026', 'time' => '09.30', 'by' => 'Yuni Astuti'],
            ['id' => 3, 'itemId' => 6, 'item' => 'Semir Ban Gloss', 'sku' => 'TRE-006', 'unit' => 'liter', 'type' => 'keluar', 'quantity' => -1, 'quantityAfter' => 2, 'note' => 'Pemakaian layanan add-on', 'date' => '3 Agu 2026', 'time' => '08.50', 'by' => 'Bagas Pratomo'],
            ['id' => 4, 'itemId' => 4, 'item' => 'Nano Ceramic Coating 9H', 'sku' => 'CTG-004', 'unit' => 'set', 'type' => 'keluar', 'quantity' => -1, 'quantityAfter' => 4, 'note' => 'Job coating B 5150 AB', 'date' => '3 Agu 2026', 'time' => '09.20', 'by' => 'Tim Detailing'],
            ['id' => 5, 'itemId' => 5, 'item' => 'Microfiber Towel 40x40', 'sku' => 'MFT-005', 'unit' => 'pcs', 'type' => 'penyesuaian', 'quantity' => -4, 'quantityAfter' => 96, 'note' => 'Rusak / sobek saat pemakaian', 'date' => '1 Agu 2026', 'time' => '18.20', 'by' => 'Sinta Dewi'],
            ['id' => 6, 'itemId' => 5, 'item' => 'Microfiber Towel 40x40', 'sku' => 'MFT-005', 'unit' => 'pcs', 'type' => 'masuk', 'quantity' => 36, 'quantityAfter' => 100, 'note' => 'Pembelian 3 lusin', 'date' => '1 Agu 2026', 'time' => '16.05', 'by' => 'Yuni Astuti'],
            ['id' => 7, 'itemId' => 2, 'item' => 'Shampoo Mobil Konsentrat', 'sku' => 'SHP-002', 'unit' => 'liter', 'type' => 'keluar', 'quantity' => -6, 'quantityAfter' => 24, 'note' => 'Pemakaian mingguan', 'date' => '31 Jul 2026', 'time' => '19.00', 'by' => 'Agus Setiawan'],
            ['id' => 8, 'itemId' => 10, 'item' => 'Sarung Tangan Karet', 'sku' => 'SRG-010', 'unit' => 'pasang', 'type' => 'keluar', 'quantity' => -8, 'quantityAfter' => 5, 'note' => 'Distribusi ke crew', 'date' => '31 Jul 2026', 'time' => '07.45', 'by' => 'Sinta Dewi'],
        ];
    }

    /**
     * @param  array{q: string, category: string, status: string, stock: string, page: int, movementPage: int}  $filters
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|null>}
     */
    public static function page(array $filters): array
    {
        $needle = Str::lower($filters['q']);

        $rows = collect(self::items())
            ->filter(function (array $item) use ($filters, $needle): bool {
                if ($filters['category'] !== 'Semua' && $item['category'] !== $filters['category']) {
                    return false;
                }

                if ($filters['stock'] === 'Stok menipis' && ! $item['isLowStock']) {
                    return false;
                }

                if ($needle === '') {
                    return true;
                }

                return Str::contains(Str::lower(implode(' ', [
                    $item['name'],
                    $item['sku'],
                    $item['supplier'],
                ])), $needle);
            })
            ->values()
            ->all();

        return Paginated::fromArray($rows, $filters['page'], StockQueries::PER_PAGE);
    }

    /**
     * @param  array{q: string, category: string, status: string, stock: string, page: int, movementPage: int}  $filters
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|null>}
     */
    public static function movementPage(array $filters): array
    {
        return Paginated::fromArray(
            self::movements(),
            $filters['movementPage'],
            StockQueries::MOVEMENTS_PER_PAGE,
        );
    }

    /**
     * @return array{totalItems: int, lowStock: int}
     */
    public static function stats(): array
    {
        $items = collect(self::items());

        return [
            'totalItems' => $items->count(),
            'lowStock' => $items->where('isLowStock', true)->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function itemOptions(): array
    {
        return array_map(
            fn (array $item): array => [
                'id' => $item['id'],
                'sku' => $item['sku'],
                'name' => $item['name'],
                'unit' => $item['unit'],
                'quantity' => $item['quantity'],
                'minQuantity' => $item['minQuantity'],
                'isLowStock' => $item['isLowStock'],
            ],
            self::items(),
        );
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
    public static function suppliers(): array
    {
        return array_values(array_unique(array_column(self::items(), 'supplier')));
    }

    /**
     * @return list<string>
     */
    public static function movementTypes(): array
    {
        return StockQueries::MOVEMENT_TYPES;
    }

    /**
     * The two figures the live presenter derives rather than stores.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function decorate(array $item): array
    {
        return [
            ...$item,
            'isLowStock' => $item['quantity'] <= $item['minQuantity'],
            'stockValue' => $item['quantity'] * $item['unitCost'],
            'notes' => '',
            'isActive' => true,
        ];
    }
}
