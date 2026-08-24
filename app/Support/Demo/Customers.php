<?php

namespace App\Support\Demo;

use Illuminate\Support\Str;

/**
 * Centralized customer database (BR-07) and the loyalty/stamp records (BR-02).
 */
class Customers
{
    /**
     * Member and loyalty totals shared with the member module and dashboard.
     *
     * @return array{total: int, active: int, redeemedStamps: int, rewardsClaimed: int}
     */
    public static function summary(): array
    {
        $members = self::all();
        $redemptions = array_values(array_filter(
            self::stampHistory(),
            fn (array $entry): bool => $entry['type'] === 'redeem',
        ));

        return [
            'total' => count($members),
            'active' => count(array_filter(
                $members,
                fn (array $member): bool => $member['status'] === 'aktif',
            )),
            'redeemedStamps' => abs(array_sum(array_column($redemptions, 'stamps'))),
            'rewardsClaimed' => count($redemptions),
        ];
    }

    /**
     * @return list<array{id: int, name: string, memberId: string, phone: string, email: string, vehicle: string, plate: string, vehicles: list<array{name: string, plate: string, type: string, isPrimary: bool}>, stamps: int, lifetimeStamps: int, visits: int, spend: int, joinedAt: string, lastVisit: string, initials: string, status: string, hasAccount: bool}>
     */
    public static function all(): array
    {
        $customers = [
            ['id' => 1, 'name' => 'Hendra Gunawan', 'memberId' => 'ZW-2023-0031', 'phone' => '0812-1100-2255', 'email' => 'hendra.g@mail.com', 'vehicle' => 'Mazda CX-5', 'plate' => 'B 5150 AB', 'stamps' => 9, 'lifetimeStamps' => 78, 'visits' => 64, 'spend' => 18450000, 'joinedAt' => 'Mar 2023', 'lastVisit' => 'Hari ini, 09.20', 'initials' => 'HG', 'status' => 'aktif', 'hasAccount' => true],
            ['id' => 2, 'name' => 'Rizky Pratama', 'memberId' => 'ZW-2023-0118', 'phone' => '0813-7788-1200', 'email' => 'rizky.pratama@mail.com', 'vehicle' => 'Honda Civic', 'plate' => 'B 9090 RS', 'stamps' => 6, 'lifetimeStamps' => 61, 'visits' => 51, 'spend' => 14200000, 'joinedAt' => 'Jun 2023', 'lastVisit' => 'Hari ini, 08.05', 'initials' => 'RP', 'status' => 'aktif', 'hasAccount' => true],
            ['id' => 3, 'name' => 'Budi Santoso', 'memberId' => 'ZW-2024-0412', 'phone' => '0812-3456-7890', 'email' => 'budi.santoso@mail.com', 'vehicle' => 'Toyota Avanza', 'plate' => 'B 1234 CDE', 'stamps' => 7, 'lifetimeStamps' => 52, 'visits' => 42, 'spend' => 9850000, 'joinedAt' => 'Jan 2024', 'lastVisit' => 'Kemarin, 16.40', 'initials' => 'BS', 'status' => 'aktif', 'hasAccount' => true],
            ['id' => 4, 'name' => 'Maya Kusuma', 'memberId' => 'ZW-2024-0455', 'phone' => '0857-2210-8890', 'email' => 'maya.k@mail.com', 'vehicle' => 'Mitsubishi Xpander', 'plate' => 'B 4412 ZX', 'stamps' => 4, 'lifetimeStamps' => 31, 'visits' => 25, 'spend' => 7320000, 'joinedAt' => 'Feb 2024', 'lastVisit' => '2 hari lalu', 'initials' => 'MK', 'status' => 'aktif', 'hasAccount' => true],
            ['id' => 5, 'name' => 'Siti Rahmawati', 'memberId' => 'ZW-2024-0511', 'phone' => '0821-4455-6677', 'email' => 'siti.rahma@mail.com', 'vehicle' => 'Honda Brio', 'plate' => 'B 8821 KL', 'stamps' => 2, 'lifetimeStamps' => 26, 'visits' => 21, 'spend' => 5140000, 'joinedAt' => 'Apr 2024', 'lastVisit' => '3 hari lalu', 'initials' => 'SR', 'status' => 'aktif', 'hasAccount' => true],
            ['id' => 6, 'name' => 'Fajar Nugroho', 'memberId' => 'ZW-2024-0602', 'phone' => '0878-9012-3344', 'email' => 'fajar.n@mail.com', 'vehicle' => 'Toyota Fortuner', 'plate' => 'B 7788 JK', 'stamps' => 5, 'lifetimeStamps' => 24, 'visits' => 18, 'spend' => 6480000, 'joinedAt' => 'Mei 2024', 'lastVisit' => 'Minggu lalu', 'initials' => 'FN', 'status' => 'aktif', 'hasAccount' => false],
            ['id' => 7, 'name' => 'Clara Halim', 'memberId' => 'ZW-2024-0733', 'phone' => '0811-3030-4040', 'email' => 'clara.halim@mail.com', 'vehicle' => 'Suzuki Ertiga', 'plate' => 'B 1717 PO', 'stamps' => 3, 'lifetimeStamps' => 19, 'visits' => 15, 'spend' => 3980000, 'joinedAt' => 'Jul 2024', 'lastVisit' => 'Minggu lalu', 'initials' => 'CH', 'status' => 'aktif', 'hasAccount' => true],
            ['id' => 8, 'name' => 'Andi Wijaya', 'memberId' => 'ZW-2024-1120', 'phone' => '0852-6677-8899', 'email' => 'andi.wijaya@mail.com', 'vehicle' => 'Yamaha NMax', 'plate' => 'B 6677 TG', 'stamps' => 8, 'lifetimeStamps' => 11, 'visits' => 9, 'spend' => 890000, 'joinedAt' => 'Nov 2024', 'lastVisit' => 'Hari ini, 10.15', 'initials' => 'AW', 'status' => 'aktif', 'hasAccount' => false],
            ['id' => 9, 'name' => 'Dewi Lestari', 'memberId' => 'ZW-2025-0104', 'phone' => '0813-2233-5566', 'email' => 'dewi.lestari@mail.com', 'vehicle' => 'Daihatsu Ayla', 'plate' => 'B 3311 QW', 'stamps' => 6, 'lifetimeStamps' => 7, 'visits' => 6, 'spend' => 620000, 'joinedAt' => 'Jan 2025', 'lastVisit' => '4 hari lalu', 'initials' => 'DL', 'status' => 'aktif', 'hasAccount' => true],
            ['id' => 10, 'name' => 'Nadia Putri', 'memberId' => 'ZW-2025-0318', 'phone' => '0896-1122-3344', 'email' => 'nadia.putri@mail.com', 'vehicle' => 'Honda Vario', 'plate' => 'B 2255 LM', 'stamps' => 4, 'lifetimeStamps' => 5, 'visits' => 4, 'spend' => 240000, 'joinedAt' => 'Mar 2025', 'lastVisit' => 'Hari ini, 11.02', 'initials' => 'NP', 'status' => 'aktif', 'hasAccount' => false],
            ['id' => 11, 'name' => 'Gilang Ramadhan', 'memberId' => 'ZW-2025-0502', 'phone' => '0817-8899-1010', 'email' => 'gilang.r@mail.com', 'vehicle' => 'Wuling Almaz', 'plate' => 'B 9021 HH', 'stamps' => 1, 'lifetimeStamps' => 3, 'visits' => 3, 'spend' => 410000, 'joinedAt' => 'Mei 2025', 'lastVisit' => '2 minggu lalu', 'initials' => 'GR', 'status' => 'tidak aktif', 'hasAccount' => false],
            ['id' => 12, 'name' => 'Putri Amelia', 'memberId' => 'ZW-2025-0640', 'phone' => '0838-4321-7788', 'email' => 'putri.amelia@mail.com', 'vehicle' => 'Hyundai Stargazer', 'plate' => 'B 3388 VN', 'stamps' => 2, 'lifetimeStamps' => 2, 'visits' => 2, 'spend' => 205000, 'joinedAt' => 'Jun 2025', 'lastVisit' => '3 minggu lalu', 'initials' => 'PA', 'status' => 'tidak aktif', 'hasAccount' => true],
        ];

        $additionalVehicles = [
            1 => [
                ['name' => 'Toyota Alphard', 'plate' => 'B 2020 HG', 'type' => 'Mobil', 'isPrimary' => false],
            ],
            3 => [
                ['name' => 'Honda Vario 160', 'plate' => 'B 5566 TY', 'type' => 'Motor', 'isPrimary' => false],
            ],
            6 => [
                ['name' => 'Honda HR-V', 'plate' => 'B 7789 JK', 'type' => 'Mobil', 'isPrimary' => false],
            ],
        ];

        return array_map(static function (array $customer) use ($additionalVehicles): array {
            $customer['vehicles'] = [
                [
                    'name' => $customer['vehicle'],
                    'plate' => $customer['plate'],
                    'type' => Str::contains($customer['vehicle'], ['Yamaha', 'Vario']) ? 'Motor' : 'Mobil',
                    'isPrimary' => true,
                ],
                ...($additionalVehicles[$customer['id']] ?? []),
            ];

            return $customer;
        }, $customers);
    }

    /**
     * The signed-in customer for the member portal demo (BR-01, BR-02).
     *
     * @return array{id: int, name: string, memberId: string, phone: string, email: string, stamps: int, lifetimeStamps: int, visits: int, spend: int, joinedAt: string, initials: string, referralCode: string, rewardsClaimed: int, vehicles: list<array{name: string, plate: string, type: string, isPrimary: bool}>}
     */
    public static function member(): array
    {
        return [
            'id' => 3,
            'name' => 'Budi Santoso',
            'memberId' => 'ZW-2024-0412',
            'phone' => '0812-3456-7890',
            'email' => 'budi.santoso@mail.com',
            'stamps' => 7,
            'lifetimeStamps' => 52,
            'visits' => 42,
            'spend' => 9850000,
            'joinedAt' => '12 Januari 2024',
            'initials' => 'BS',
            'referralCode' => 'BUDI-ZEN25',
            'rewardsClaimed' => 5,
            'vehicles' => [
                ['name' => 'Toyota Avanza', 'plate' => 'B 1234 CDE', 'type' => 'Mobil', 'isPrimary' => true],
                ['name' => 'Honda Vario 160', 'plate' => 'B 5566 TY', 'type' => 'Motor', 'isPrimary' => false],
            ],
        ];
    }

    /**
     * Stamp ledger for the signed-in member (BR-02).
     *
     * @return list<array{id: int, title: string, detail: string, stamps: int, type: string, date: string, icon: string}>
     */
    public static function stampHistory(): array
    {
        return [
            ['id' => 1, 'title' => 'Cuci Mobil + Wax', 'detail' => 'B 1234 CDE', 'stamps' => 1, 'type' => 'earn', 'date' => '2 Agu 2026, 16.40', 'icon' => '✨'],
            ['id' => 2, 'title' => 'Tukar reward Parfum Mobil', 'detail' => 'Voucher ZW-PRF-8821 diterbitkan', 'stamps' => -4, 'type' => 'redeem', 'date' => '28 Jul 2026, 10.12', 'icon' => '🎁'],
            ['id' => 4, 'title' => 'Snow Wash Premium', 'detail' => 'B 1234 CDE', 'stamps' => 2, 'type' => 'earn', 'date' => '26 Jul 2026, 09.30', 'icon' => '❄️'],
            ['id' => 5, 'title' => 'Bonus referral', 'detail' => 'Teman kamu bergabung: Clara H.', 'stamps' => 1, 'type' => 'bonus', 'date' => '19 Jul 2026, 14.05', 'icon' => '👥'],
            ['id' => 6, 'title' => 'Deep Clean Interior', 'detail' => 'B 1234 CDE', 'stamps' => 2, 'type' => 'earn', 'date' => '12 Jul 2026, 11.20', 'icon' => '🧽'],
            ['id' => 7, 'title' => 'Tukar reward Vacuum Interior', 'detail' => 'Voucher ZW-VAC-4410 diterbitkan', 'stamps' => -5, 'type' => 'redeem', 'date' => '5 Jul 2026, 15.48', 'icon' => '🎁'],
            ['id' => 8, 'title' => 'Cuci Motor + Semir', 'detail' => 'B 5566 TY', 'stamps' => 1, 'type' => 'earn', 'date' => '1 Jul 2026, 08.15', 'icon' => '🛵'],
        ];
    }

    /**
     * Wash history for the signed-in member (BR-02).
     *
     * @return list<array{id: int, service: string, vehicle: string, date: string, total: int, stamps: int, rating: int, status: string}>
     */
    public static function washHistory(): array
    {
        return [
            ['id' => 1, 'service' => 'Cuci Mobil + Wax', 'vehicle' => 'B 1234 CDE', 'date' => '2 Agu 2026', 'total' => 85000, 'stamps' => 1, 'rating' => 5, 'status' => 'selesai'],
            ['id' => 2, 'service' => 'Snow Wash Premium', 'vehicle' => 'B 1234 CDE', 'date' => '26 Jul 2026', 'total' => 120000, 'stamps' => 2, 'rating' => 5, 'status' => 'selesai'],
            ['id' => 3, 'service' => 'Deep Clean Interior', 'vehicle' => 'B 1234 CDE', 'date' => '12 Jul 2026', 'total' => 150000, 'stamps' => 2, 'rating' => 4, 'status' => 'selesai'],
            ['id' => 4, 'service' => 'Cuci Motor + Semir', 'vehicle' => 'B 5566 TY', 'date' => '1 Jul 2026', 'total' => 35000, 'stamps' => 1, 'rating' => 5, 'status' => 'selesai'],
            ['id' => 5, 'service' => 'Cuci Mobil Reguler', 'vehicle' => 'B 1234 CDE', 'date' => '18 Jun 2026', 'total' => 45000, 'stamps' => 1, 'rating' => 5, 'status' => 'selesai'],
            ['id' => 6, 'service' => 'Salon Jok & Karpet', 'vehicle' => 'B 1234 CDE', 'date' => '2 Jun 2026', 'total' => 350000, 'stamps' => 3, 'rating' => 4, 'status' => 'selesai'],
        ];
    }

    /**
     * Vouchers already claimed by the signed-in member (BR-04, read-only).
     *
     * @return list<array{id: int, name: string, code: string, expiresAt: string, icon: string, status: string}>
     */
    public static function vouchers(): array
    {
        return [
            ['id' => 1, 'name' => 'Gratis Parfum Mobil', 'code' => 'ZW-PRF-8821', 'expiresAt' => 'Berlaku s/d 31 Agu 2026', 'icon' => '💨', 'status' => 'aktif'],
            ['id' => 2, 'name' => 'Gratis Vacuum Interior', 'code' => 'ZW-VAC-4410', 'expiresAt' => 'Berlaku s/d 20 Agu 2026', 'icon' => '🧽', 'status' => 'aktif'],
            ['id' => 3, 'name' => 'Gratis Semir Ban', 'code' => 'ZW-SMR-2201', 'expiresAt' => 'Terpakai 5 Jul 2026', 'icon' => '⚫', 'status' => 'terpakai'],
        ];
    }
}
