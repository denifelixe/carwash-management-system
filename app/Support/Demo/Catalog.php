<?php

namespace App\Support\Demo;

use App\Support\Admin\ServiceCategoryGroups;

/**
 * Service catalog (BR-03) and reward catalog (BR-04, BR-13).
 */
class Catalog
{
    /**
     * Services shared by the POS grid, order builder, and customer catalog.
     *
     * @return list<array{id: int, name: string, category: string, categoryGroup: string, price: int, variations: array<string, list<string>>|null, serviceVariations: list<array{id: int, variations: array<string, string>|null, price: int, isActive: bool}>, stamps: int, icon: string, description: string, popular: bool, isActive: bool}>
     */
    public static function services(): array
    {
        $services = [
            ['id' => 1, 'name' => 'Cuci Mobil Reguler', 'category' => 'Cuci Mobil', 'price' => 45000, 'stamps' => 1, 'icon' => '🚗', 'description' => 'Cuci body, sela ban, dan lap kering menyeluruh.', 'popular' => true, 'isActive' => true],
            ['id' => 2, 'name' => 'Cuci Mobil + Wax', 'category' => 'Cuci Mobil', 'price' => 85000, 'stamps' => 1, 'icon' => '✨', 'description' => 'Cuci reguler plus lapisan wax agar cat lebih berkilau.', 'popular' => true, 'isActive' => true],
            ['id' => 3, 'name' => 'Snow Wash Premium', 'category' => 'Cuci Mobil', 'price' => 120000, 'stamps' => 2, 'icon' => '❄️', 'description' => 'Busa salju pH netral, aman untuk cat dan lapisan coating.', 'popular' => true, 'isActive' => true],
            ['id' => 4, 'name' => 'Cuci Motor Reguler', 'category' => 'Cuci Motor', 'price' => 20000, 'stamps' => 1, 'icon' => '🏍️', 'description' => 'Cuci motor cepat dengan pengeringan blower.', 'popular' => false, 'isActive' => true],
            ['id' => 5, 'name' => 'Cuci Motor + Semir', 'category' => 'Cuci Motor', 'price' => 35000, 'stamps' => 1, 'icon' => '🛵', 'description' => 'Cuci motor lengkap dengan semir ban dan bodi mengkilap.', 'popular' => false, 'isActive' => true],
            ['id' => 6, 'name' => 'Poles Body Detailing', 'category' => 'Detailing', 'price' => 450000, 'stamps' => 4, 'icon' => '💎', 'description' => 'Hilangkan baret halus dan kembalikan kilau cat mobil.', 'popular' => false, 'isActive' => true],
            ['id' => 7, 'name' => 'Nano Ceramic Coating', 'category' => 'Detailing', 'price' => 1500000, 'stamps' => 10, 'icon' => '🛡️', 'description' => 'Proteksi cat hingga 12 bulan, anti air dan gores ringan.', 'popular' => false, 'isActive' => true],
            ['id' => 8, 'name' => 'Engine Bay Cleaning', 'category' => 'Detailing', 'price' => 90000, 'stamps' => 1, 'icon' => '🔧', 'description' => 'Bersihkan ruang mesin dari kerak oli dan debu.', 'popular' => false, 'isActive' => true],
            ['id' => 9, 'name' => 'Deep Clean Interior', 'category' => 'Interior', 'price' => 150000, 'stamps' => 2, 'icon' => '🧽', 'description' => 'Vacuum menyeluruh, bersihkan dashboard, dan door trim.', 'popular' => true, 'isActive' => true],
            ['id' => 10, 'name' => 'Salon Jok & Karpet', 'category' => 'Interior', 'price' => 350000, 'stamps' => 3, 'icon' => '🪑', 'description' => 'Cuci jok, karpet, dan plafon dengan mesin extractor.', 'popular' => false, 'isActive' => true],
            ['id' => 11, 'name' => 'Parfum & Anti Jamur Kaca', 'category' => 'Add-on', 'price' => 60000, 'stamps' => 0, 'icon' => '💨', 'description' => 'Wangi kabin tahan lama plus kaca bebas jamur.', 'popular' => false, 'isActive' => true],
            ['id' => 12, 'name' => 'Semir Ban Premium', 'category' => 'Add-on', 'price' => 25000, 'stamps' => 0, 'icon' => '⚫', 'description' => 'Ban hitam pekat mengkilap tahan hingga 2 minggu.', 'popular' => false, 'isActive' => true],
            [
                'id' => 13,
                'name' => 'Coating Lite',
                'category' => 'Coating Mobil',
                'price' => 800000,
                'variations' => ['Ukuran' => ['Small', 'Medium', 'Large', 'Extra Large']],
                'serviceVariations' => [
                    ['id' => 13, 'variations' => ['Ukuran' => 'Small'], 'price' => 800000, 'isActive' => true],
                    ['id' => 14, 'variations' => ['Ukuran' => 'Medium'], 'price' => 950000, 'isActive' => true],
                    ['id' => 15, 'variations' => ['Ukuran' => 'Large'], 'price' => 1100000, 'isActive' => true],
                    ['id' => 16, 'variations' => ['Ukuran' => 'Extra Large'], 'price' => 1250000, 'isActive' => true],
                ],
                'stamps' => 0,
                'icon' => '🛡️',
                'description' => 'Coating Lite untuk perlindungan cat kendaraan.',
                'popular' => false,
                'isActive' => true,
            ],
        ];

        return array_map(function (array $service): array {
            $service['categoryGroup'] = ServiceCategoryGroups::defaultFor($service['category']);

            if (isset($service['serviceVariations'])) {
                return $service;
            }

            return [
                ...$service,
                'variations' => null,
                'serviceVariations' => [[
                    'id' => $service['id'],
                    'variations' => null,
                    'price' => $service['price'],
                    'isActive' => $service['isActive'],
                ]],
            ];
        }, $services);
    }

    /**
     * Reward catalog with variation discounts offered at the till.
     *
     * @return list<array{id: int, name: string, description: string, requiredStamps: int, applicableVariations: list<array{serviceVariationId: int, quantity: int, discountPercent: int}>, icon: string, status: string, stock: int, redeemed: int}>
     */
    public static function rewards(): array
    {
        return [
            ['id' => 1, 'name' => 'Gratis Semir Ban', 'description' => 'Semir ban premium gratis untuk satu kali kunjungan.', 'requiredStamps' => 3, 'applicableVariations' => [['serviceVariationId' => 12, 'quantity' => 1, 'discountPercent' => 100]], 'icon' => '⚫', 'status' => 'aktif', 'stock' => 50, 'redeemed' => 34],
            ['id' => 2, 'name' => 'Gratis Parfum Mobil', 'description' => 'Wangi kabin tahan lama untuk sekali kunjungan.', 'requiredStamps' => 4, 'applicableVariations' => [], 'icon' => '💨', 'status' => 'aktif', 'stock' => 60, 'redeemed' => 28],
            ['id' => 3, 'name' => 'Gratis Vacuum Interior', 'description' => 'Vacuum kabin menyeluruh tanpa biaya tambahan.', 'requiredStamps' => 5, 'applicableVariations' => [], 'icon' => '🧽', 'status' => 'aktif', 'stock' => 35, 'redeemed' => 19],
            ['id' => 4, 'name' => 'Gratis Cuci Motor', 'description' => 'Tukar stempel dengan satu kali cuci motor reguler.', 'requiredStamps' => 6, 'applicableVariations' => [['serviceVariationId' => 4, 'quantity' => 1, 'discountPercent' => 100]], 'icon' => '🏍️', 'status' => 'aktif', 'stock' => 40, 'redeemed' => 22],
            ['id' => 5, 'name' => 'Diskon 50% Snow Wash', 'description' => 'Potongan setengah harga untuk snow wash premium.', 'requiredStamps' => 8, 'applicableVariations' => [['serviceVariationId' => 3, 'quantity' => 1, 'discountPercent' => 50]], 'icon' => '❄️', 'status' => 'aktif', 'stock' => 25, 'redeemed' => 11],
            ['id' => 6, 'name' => 'Gratis Cuci Mobil Reguler', 'description' => 'Satu kali cuci mobil reguler gratis.', 'requiredStamps' => 10, 'applicableVariations' => [['serviceVariationId' => 1, 'quantity' => 1, 'discountPercent' => 100]], 'icon' => '🚗', 'status' => 'aktif', 'stock' => 30, 'redeemed' => 15],
            ['id' => 7, 'name' => 'Tumbler ZenWash Eksklusif', 'description' => 'Merchandise tumbler stainless edisi terbatas.', 'requiredStamps' => 12, 'applicableVariations' => [], 'icon' => '🥤', 'status' => 'aktif', 'stock' => 12, 'redeemed' => 6],
            ['id' => 8, 'name' => 'Diskon 25% Poles Body', 'description' => 'Potongan seperempat harga untuk layanan poles body detailing.', 'requiredStamps' => 15, 'applicableVariations' => [['serviceVariationId' => 6, 'quantity' => 1, 'discountPercent' => 25]], 'icon' => '💎', 'status' => 'aktif', 'stock' => 18, 'redeemed' => 4],
            ['id' => 9, 'name' => 'Gratis Deep Clean Interior', 'description' => 'Interior detailing menyeluruh tanpa biaya.', 'requiredStamps' => 20, 'applicableVariations' => [['serviceVariationId' => 9, 'quantity' => 1, 'discountPercent' => 100]], 'icon' => '🪑', 'status' => 'aktif', 'stock' => 10, 'redeemed' => 2],
            ['id' => 10, 'name' => 'Gratis Nano Ceramic Coating', 'description' => 'Reward utama: coating penuh selama 12 bulan proteksi.', 'requiredStamps' => 40, 'applicableVariations' => [['serviceVariationId' => 7, 'quantity' => 1, 'discountPercent' => 100]], 'icon' => '🛡️', 'status' => 'nonaktif', 'stock' => 3, 'redeemed' => 1],
        ];
    }

    /**
     * Redemptions made at the till, in the shape RewardPresenter::redemption() uses.
     *
     * @return list<array{id: int, date: string, time: string, member: string, memberId: string, order: string, reward: string, stamps: int, discount: int, by: string}>
     */
    public static function rewardRedemptions(): array
    {
        return [
            ['id' => 6, 'date' => '2 Agu 2026', 'time' => '16.42', 'member' => 'Siti Rahmawati', 'memberId' => 'ZW-2024-0388', 'order' => 'ORD-20260802-006', 'reward' => 'Gratis Semir Ban', 'stamps' => 3, 'discount' => 25000, 'by' => 'Rina Kasir'],
            ['id' => 5, 'date' => '28 Jul 2026', 'time' => '10.12', 'member' => 'Budi Santoso', 'memberId' => 'ZW-2024-0412', 'order' => 'ORD-20260728-014', 'reward' => 'Gratis Parfum Mobil', 'stamps' => 4, 'discount' => 0, 'by' => 'Rina Kasir'],
            ['id' => 4, 'date' => '21 Jul 2026', 'time' => '13.05', 'member' => 'Andi Wijaya', 'memberId' => 'ZW-2024-0451', 'order' => 'ORD-20260721-009', 'reward' => 'Gratis Cuci Motor', 'stamps' => 6, 'discount' => 20000, 'by' => 'Dimas Kasir'],
            ['id' => 3, 'date' => '12 Jul 2026', 'time' => '11.40', 'member' => 'Dewi Lestari', 'memberId' => 'ZW-2024-0297', 'order' => 'ORD-20260712-021', 'reward' => 'Gratis Cuci Mobil Reguler', 'stamps' => 10, 'discount' => 45000, 'by' => 'Rina Kasir'],
            ['id' => 2, 'date' => '5 Jul 2026', 'time' => '15.48', 'member' => 'Budi Santoso', 'memberId' => 'ZW-2024-0412', 'order' => 'ORD-20260705-017', 'reward' => 'Gratis Vacuum Interior', 'stamps' => 5, 'discount' => 0, 'by' => 'Dimas Kasir'],
            ['id' => 1, 'date' => '29 Jun 2026', 'time' => '09.20', 'member' => 'Rudi Hartono', 'memberId' => 'ZW-2024-0510', 'order' => 'ORD-20260629-003', 'reward' => 'Diskon 50% Snow Wash', 'stamps' => 8, 'discount' => 60000, 'by' => 'Rina Kasir'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function serviceCategories(): array
    {
        return array_values(array_unique(array_column(self::services(), 'category')));
    }
}
