<?php

namespace App\Support\Carwash;

/**
 * Daily operations: orders/transactions (BR-05), live queue, and bookings (BR-08).
 */
class Operations
{
    /**
     * `paidAmount` is the rupiah already collected at the cashier, so the three
     * payment states stay derivable: 0 is `belum bayar`, anything between 0 and
     * `total` is `dp`, and `total` is `lunas`.
     *
     * A reward is redeemed by the front office when the order is written up, so
     * `total` is already net of `discount`; `reward` names what was traded in and
     * is `'—'` when nothing was.
     *
     * @return list<array{id: int, orderNo: string, invoice: string, time: string, customerId: int|null, customer: string, phone: string, vehicle: string, plate: string, items: string, serviceIds: list<int>, total: int, discount: int, reward: string, paidAmount: int, payment: string, paymentStatus: string, status: string, stampsEarned: int, crew: string, bay: string, source: string}>
     */
    public static function orders(): array
    {
        return [
            ['id' => 1, 'orderNo' => 'ORD-25080312', 'invoice' => 'ZW-25080312', 'time' => '11.02', 'customerId' => 10, 'customer' => 'Nadia Putri', 'phone' => '0896-1122-3344', 'vehicle' => 'Honda Vario', 'plate' => 'B 2255 LM', 'items' => 'Cuci Motor + Semir', 'serviceIds' => [5], 'total' => 35000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 35000, 'payment' => 'QRIS', 'paymentStatus' => 'lunas', 'status' => 'proses', 'stampsEarned' => 1, 'crew' => 'Bagas', 'bay' => 'Bay 3', 'source' => 'walk-in'],
            ['id' => 2, 'orderNo' => 'ORD-25080311', 'invoice' => 'ZW-25080311', 'time' => '10.40', 'customerId' => 8, 'customer' => 'Andi Wijaya', 'phone' => '0852-6677-8899', 'vehicle' => 'Yamaha NMax', 'plate' => 'B 6677 TG', 'items' => 'Cuci Motor Reguler', 'serviceIds' => [4], 'total' => 20000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 20000, 'payment' => 'Tunai', 'paymentStatus' => 'lunas', 'status' => 'menunggu', 'stampsEarned' => 1, 'crew' => 'Menunggu crew', 'bay' => '—', 'source' => 'walk-in'],
            ['id' => 3, 'orderNo' => 'ORD-25080310', 'invoice' => 'ZW-25080310', 'time' => '10.15', 'customerId' => 4, 'customer' => 'Maya Kusuma', 'phone' => '0857-2210-8890', 'vehicle' => 'Mitsubishi Xpander', 'plate' => 'B 4412 ZX', 'items' => 'Deep Clean Interior, Parfum & Anti Jamur Kaca', 'serviceIds' => [9, 11], 'total' => 210000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 210000, 'payment' => 'Debit', 'paymentStatus' => 'lunas', 'status' => 'proses', 'stampsEarned' => 2, 'crew' => 'Tim Interior', 'bay' => 'Bay 4', 'source' => 'booking'],
            ['id' => 4, 'orderNo' => 'ORD-25080309', 'invoice' => 'ZW-25080309', 'time' => '09.52', 'customerId' => null, 'customer' => 'Umum (non-member)', 'phone' => '—', 'vehicle' => 'Toyota Calya', 'plate' => 'B 1290 UY', 'items' => 'Cuci Mobil Reguler', 'serviceIds' => [1], 'total' => 45000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 45000, 'payment' => 'Tunai', 'paymentStatus' => 'lunas', 'status' => 'selesai', 'stampsEarned' => 0, 'crew' => 'Agus', 'bay' => 'Bay 1', 'source' => 'walk-in'],
            ['id' => 5, 'orderNo' => 'ORD-25080308', 'invoice' => 'ZW-25080308', 'time' => '09.20', 'customerId' => 1, 'customer' => 'Hendra Gunawan', 'phone' => '0812-1100-2255', 'vehicle' => 'Mazda CX-5', 'plate' => 'B 5150 AB', 'items' => 'Nano Ceramic Coating', 'serviceIds' => [7], 'total' => 1500000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 1500000, 'payment' => 'Transfer', 'paymentStatus' => 'lunas', 'status' => 'proses', 'stampsEarned' => 10, 'crew' => 'Tim Detailing', 'bay' => 'Bay 1', 'source' => 'booking'],
            ['id' => 6, 'orderNo' => 'ORD-25080307', 'invoice' => 'ZW-25080307', 'time' => '08.45', 'customerId' => 5, 'customer' => 'Siti Rahmawati', 'phone' => '0821-4455-6677', 'vehicle' => 'Honda Brio', 'plate' => 'B 8821 KL', 'items' => 'Cuci Mobil Reguler, Semir Ban Premium', 'serviceIds' => [1, 12], 'total' => 45000, 'discount' => 25000, 'reward' => 'Gratis Semir Ban', 'paidAmount' => 45000, 'payment' => 'QRIS', 'paymentStatus' => 'lunas', 'status' => 'selesai', 'stampsEarned' => 1, 'crew' => 'Agus', 'bay' => 'Bay 2', 'source' => 'walk-in'],
            ['id' => 7, 'orderNo' => 'ORD-25080306', 'invoice' => 'ZW-25080306', 'time' => '08.05', 'customerId' => 2, 'customer' => 'Rizky Pratama', 'phone' => '0813-7788-1200', 'vehicle' => 'Honda Civic', 'plate' => 'B 9090 RS', 'items' => 'Snow Wash Premium, Engine Bay Cleaning', 'serviceIds' => [3, 8], 'total' => 210000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 210000, 'payment' => 'QRIS', 'paymentStatus' => 'lunas', 'status' => 'proses', 'stampsEarned' => 3, 'crew' => 'Agus & Deni', 'bay' => 'Bay 2', 'source' => 'walk-in'],
            ['id' => 8, 'orderNo' => 'ORD-25080305', 'invoice' => 'ZW-25080305', 'time' => '07.48', 'customerId' => null, 'customer' => 'Umum (non-member)', 'phone' => '—', 'vehicle' => 'Suzuki XL7', 'plate' => 'B 7742 KD', 'items' => 'Cuci Mobil + Wax', 'serviceIds' => [2], 'total' => 85000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 85000, 'payment' => 'Tunai', 'paymentStatus' => 'lunas', 'status' => 'selesai', 'stampsEarned' => 0, 'crew' => 'Deni', 'bay' => 'Bay 3', 'source' => 'walk-in'],
            ['id' => 9, 'orderNo' => 'ORD-25080304', 'invoice' => '—', 'time' => '07.30', 'customerId' => 9, 'customer' => 'Dewi Lestari', 'phone' => '0813-2233-5566', 'vehicle' => 'Daihatsu Ayla', 'plate' => 'B 3311 QW', 'items' => 'Cuci Mobil Reguler', 'serviceIds' => [1], 'total' => 45000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 0, 'payment' => '—', 'paymentStatus' => 'belum bayar', 'status' => 'selesai', 'stampsEarned' => 1, 'crew' => 'Bagas', 'bay' => 'Bay 3', 'source' => 'walk-in'],
            ['id' => 10, 'orderNo' => 'ORD-25080303', 'invoice' => '—', 'time' => '07.15', 'customerId' => 3, 'customer' => 'Budi Santoso', 'phone' => '0812-3456-7890', 'vehicle' => 'Toyota Avanza', 'plate' => 'B 1234 CDE', 'items' => 'Cuci Mobil + Wax', 'serviceIds' => [2], 'total' => 85000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 40000, 'payment' => 'Transfer', 'paymentStatus' => 'dp', 'status' => 'menunggu', 'crew' => 'Menunggu crew', 'stampsEarned' => 1, 'bay' => '—', 'source' => 'booking'],
        ];
    }

    /**
     * Rupiah still owed across every order, so the cash summary never drifts
     * away from the order list.
     */
    public static function outstandingTotal(): int
    {
        return array_sum(array_map(
            fn (array $order): int => $order['total'] - $order['paidAmount'],
            self::orders(),
        ));
    }

    /**
     * @return list<array{id: int, plate: string, vehicle: string, owner: string, service: string, crew: string, bay: string, status: string, progress: int, eta: string}>
     */
    public static function queue(): array
    {
        return [
            ['id' => 1, 'plate' => 'B 5150 AB', 'vehicle' => 'Mazda CX-5', 'owner' => 'Hendra Gunawan', 'service' => 'Nano Ceramic Coating', 'crew' => 'Tim Detailing', 'bay' => 'Bay 1', 'status' => 'proses', 'progress' => 65, 'eta' => '± 95 menit lagi'],
            ['id' => 2, 'plate' => 'B 9090 RS', 'vehicle' => 'Honda Civic', 'owner' => 'Rizky Pratama', 'service' => 'Snow Wash Premium', 'crew' => 'Agus & Deni', 'bay' => 'Bay 2', 'status' => 'proses', 'progress' => 40, 'eta' => '± 35 menit lagi'],
            ['id' => 3, 'plate' => 'B 2255 LM', 'vehicle' => 'Honda Vario', 'owner' => 'Nadia Putri', 'service' => 'Cuci Motor + Semir', 'crew' => 'Bagas', 'bay' => 'Bay 3', 'status' => 'proses', 'progress' => 80, 'eta' => '± 5 menit lagi'],
            ['id' => 4, 'plate' => 'B 4412 ZX', 'vehicle' => 'Mitsubishi Xpander', 'owner' => 'Maya Kusuma', 'service' => 'Deep Clean Interior', 'crew' => 'Tim Interior', 'bay' => 'Bay 4', 'status' => 'proses', 'progress' => 25, 'eta' => '± 70 menit lagi'],
            ['id' => 5, 'plate' => 'B 6677 TG', 'vehicle' => 'Yamaha NMax', 'owner' => 'Andi Wijaya', 'service' => 'Cuci Motor Reguler', 'crew' => 'Menunggu crew', 'bay' => '—', 'status' => 'menunggu', 'progress' => 0, 'eta' => 'Antrean ke-1'],
            ['id' => 6, 'plate' => 'B 1234 CDE', 'vehicle' => 'Toyota Avanza', 'owner' => 'Budi Santoso', 'service' => 'Cuci Mobil + Wax', 'crew' => 'Menunggu crew', 'bay' => '—', 'status' => 'menunggu', 'progress' => 0, 'eta' => 'Antrean ke-2'],
            ['id' => 7, 'plate' => 'B 8821 KL', 'vehicle' => 'Honda Brio', 'owner' => 'Siti Rahmawati', 'service' => 'Cuci Mobil Reguler', 'crew' => 'Agus', 'bay' => 'Bay 2', 'status' => 'selesai', 'progress' => 100, 'eta' => 'Selesai 10.48'],
            ['id' => 8, 'plate' => 'B 3311 QW', 'vehicle' => 'Daihatsu Ayla', 'owner' => 'Dewi Lestari', 'service' => 'Cuci Mobil Reguler', 'crew' => 'Bagas', 'bay' => 'Bay 3', 'status' => 'selesai', 'progress' => 100, 'eta' => 'Selesai 10.05'],
        ];
    }

    /**
     * Scheduled bookings (BR-08).
     *
     * @return list<array{id: int, code: string, customerId: int|null, customer: string, phone: string, vehicle: string, plate: string, service: string, serviceId: int, date: string, time: string, dayLabel: string, status: string, estimate: int, notes: string}>
     */
    public static function bookings(): array
    {
        return [
            ['id' => 1, 'code' => 'BK-260803-01', 'customerId' => 3, 'customer' => 'Budi Santoso', 'phone' => '0812-3456-7890', 'vehicle' => 'Toyota Avanza', 'plate' => 'B 1234 CDE', 'service' => 'Cuci Mobil + Wax', 'serviceId' => 2, 'date' => '3 Agu 2026', 'time' => '13.30', 'dayLabel' => 'Hari ini', 'status' => 'terjadwal', 'estimate' => 85000, 'notes' => 'Minta lap ekstra di bagasi.'],
            ['id' => 2, 'code' => 'BK-260803-02', 'customerId' => 1, 'customer' => 'Hendra Gunawan', 'phone' => '0812-1100-2255', 'vehicle' => 'Mazda CX-5', 'plate' => 'B 5150 AB', 'service' => 'Nano Ceramic Coating', 'serviceId' => 7, 'date' => '3 Agu 2026', 'time' => '09.00', 'dayLabel' => 'Hari ini', 'status' => 'dikerjakan', 'estimate' => 1500000, 'notes' => 'Sudah DP 50% via transfer.'],
            ['id' => 3, 'code' => 'BK-260803-03', 'customerId' => 4, 'customer' => 'Maya Kusuma', 'phone' => '0857-2210-8890', 'vehicle' => 'Mitsubishi Xpander', 'plate' => 'B 4412 ZX', 'service' => 'Deep Clean Interior', 'serviceId' => 9, 'date' => '3 Agu 2026', 'time' => '10.00', 'dayLabel' => 'Hari ini', 'status' => 'dikerjakan', 'estimate' => 150000, 'notes' => 'Ada noda kopi di karpet depan.'],
            ['id' => 4, 'code' => 'BK-260803-04', 'customerId' => 7, 'customer' => 'Clara Halim', 'phone' => '0811-3030-4040', 'vehicle' => 'Suzuki Ertiga', 'plate' => 'B 1717 PO', 'service' => 'Snow Wash Premium', 'serviceId' => 3, 'date' => '3 Agu 2026', 'time' => '16.30', 'dayLabel' => 'Hari ini', 'status' => 'terjadwal', 'estimate' => 120000, 'notes' => '—'],
            ['id' => 5, 'code' => 'BK-260804-01', 'customerId' => 2, 'customer' => 'Rizky Pratama', 'phone' => '0813-7788-1200', 'vehicle' => 'Honda Civic', 'plate' => 'B 9090 RS', 'service' => 'Poles Body Detailing', 'serviceId' => 6, 'date' => '4 Agu 2026', 'time' => '08.00', 'dayLabel' => 'Besok', 'status' => 'terjadwal', 'estimate' => 450000, 'notes' => 'Baret halus di pintu kanan.'],
            ['id' => 6, 'code' => 'BK-260804-02', 'customerId' => 5, 'customer' => 'Siti Rahmawati', 'phone' => '0821-4455-6677', 'vehicle' => 'Honda Brio', 'plate' => 'B 8821 KL', 'service' => 'Salon Jok & Karpet', 'serviceId' => 10, 'date' => '4 Agu 2026', 'time' => '11.00', 'dayLabel' => 'Besok', 'status' => 'terjadwal', 'estimate' => 350000, 'notes' => '—'],
            ['id' => 7, 'code' => 'BK-260805-01', 'customerId' => 6, 'customer' => 'Fajar Nugroho', 'phone' => '0878-9012-3344', 'vehicle' => 'Toyota Fortuner', 'plate' => 'B 7788 JK', 'service' => 'Cuci Mobil + Wax', 'serviceId' => 2, 'date' => '5 Agu 2026', 'time' => '15.00', 'dayLabel' => '2 hari lagi', 'status' => 'terjadwal', 'estimate' => 85000, 'notes' => '—'],
            ['id' => 8, 'code' => 'BK-260802-07', 'customerId' => 9, 'customer' => 'Dewi Lestari', 'phone' => '0813-2233-5566', 'vehicle' => 'Daihatsu Ayla', 'plate' => 'B 3311 QW', 'service' => 'Cuci Mobil Reguler', 'serviceId' => 1, 'date' => '2 Agu 2026', 'time' => '09.30', 'dayLabel' => 'Kemarin', 'status' => 'selesai', 'estimate' => 45000, 'notes' => '—'],
            ['id' => 9, 'code' => 'BK-260802-06', 'customerId' => 11, 'customer' => 'Gilang Ramadhan', 'phone' => '0817-8899-1010', 'vehicle' => 'Wuling Almaz', 'plate' => 'B 9021 HH', 'service' => 'Snow Wash Premium', 'serviceId' => 3, 'date' => '2 Agu 2026', 'time' => '14.00', 'dayLabel' => 'Kemarin', 'status' => 'batal', 'estimate' => 120000, 'notes' => 'Pelanggan membatalkan via WhatsApp.'],
        ];
    }

    /**
     * Available booking slots offered by the admin booking form.
     *
     * @return list<string>
     */
    public static function bookingSlots(): array
    {
        return ['08.00', '09.00', '10.00', '11.00', '13.30', '15.00', '16.30', '18.00'];
    }

    /**
     * @return list<array{name: string, role: string, jobs: int, rating: float, initials: string}>
     */
    public static function crew(): array
    {
        return [
            ['name' => 'Agus Setiawan', 'role' => 'Kepala Bay', 'jobs' => 12, 'rating' => 4.9, 'initials' => 'AS'],
            ['name' => 'Deni Kurniawan', 'role' => 'Washer', 'jobs' => 10, 'rating' => 4.8, 'initials' => 'DK'],
            ['name' => 'Bagas Pratomo', 'role' => 'Washer', 'jobs' => 9, 'rating' => 4.7, 'initials' => 'BP'],
            ['name' => 'Yuni Astuti', 'role' => 'Kasir', 'jobs' => 38, 'rating' => 5.0, 'initials' => 'YA'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function paymentMethods(): array
    {
        return ['QRIS', 'Tunai', 'Debit', 'Transfer'];
    }
}
