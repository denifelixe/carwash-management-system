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
     * `total` is `sebagian`, and `total` is `lunas`.
     *
     * `total` reflects the bill after cashier discounts; `reward` names what was
     * traded in at the cashier and is `'—'` when nothing was.
     *
     * @return list<array{id: int, orderNo: string, invoice: string, date: string, time: string, bookingDate: string|null, customerId: int|null, customer: string, phone: string, vehicle: string, plate: string, items: string, serviceIds: list<int>, total: int, discount: int, reward: string, paidAmount: int, payment: string, paymentStatus: string, status: string, stampsEarned: int, crew: string, bay: string, source: string, transactions: list<array{id: string, orderId: int, date: string, time: string, type: string, amount: int, channels: string, channelBreakdown: list<array{label: string, amount: int}>}>}>
     */
    public static function orders(): array
    {
        $orders = array_map(function (array $order): array {
            $isFinalSettlement = $order['paidAmount'] >= $order['total'];
            $transactions = $order['transactions'] ?? ($order['paidAmount'] > 0
                ? [[
                    'id' => $order['invoice'] !== '—' ? $order['invoice'] : $order['orderNo'].'-TRX-1',
                    'orderId' => $order['id'],
                    'date' => $order['date'],
                    'time' => $order['time'],
                    'type' => $isFinalSettlement ? 'Pembayaran Lunas' : 'Pembayaran Sebagian',
                    'amount' => $order['paidAmount'],
                    'channels' => $order['payment'],
                    'channelBreakdown' => [
                        ['label' => $order['payment'], 'amount' => $order['paidAmount']],
                    ],
                ]]
                : []);

            $bookingDate = $order['source'] === 'booking'
                ? ($order['bookingDate'] ?? $transactions[0]['date'] ?? $order['date'])
                : null;

            return [
                ...$order,
                'bookingDate' => $bookingDate,
                'transactions' => $transactions,
            ];
        }, self::recordedOrders());

        return [...self::bookingOrders(), ...$orders];
    }

    /**
     * Every order the floor has written down, booking or walk-in. A booking only
     * appears here once it has arrived, been settled, or been cancelled — until
     * then {@see self::bookingOrders()} derives its row from the schedule.
     *
     * @return list<array{id: int, orderNo: string, invoice: string, date: string, time: string, bookingDate?: string, customerId: int|null, customer: string, phone: string, vehicle: string, plate: string, items: string, serviceIds: list<int>, total: int, discount: int, reward: string, paidAmount: int, payment: string, paymentStatus: string, status: string, stampsEarned: int, crew: string, bay: string, source: string, transactions?: list<array{id: string, orderId: int, date: string, time: string, type: string, amount: int, channels: string, channelBreakdown: list<array{label: string, amount: int}>}>}>
     */
    private static function recordedOrders(): array
    {
        return [
            ['id' => 1, 'orderNo' => self::orderNo(0, 12), 'invoice' => '—', 'date' => self::date(0), 'time' => '11.02', 'customerId' => 10, 'customer' => 'Nadia Putri', 'phone' => '0896-1122-3344', 'vehicle' => 'Honda Vario', 'plate' => 'B 2255 LM', 'items' => 'Cuci Motor + Semir', 'serviceIds' => [5], 'total' => 35000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 0, 'payment' => '—', 'paymentStatus' => 'belum bayar', 'status' => 'proses', 'stampsEarned' => 1, 'crew' => 'Bagas', 'bay' => 'Bay 3', 'source' => 'walk-in'],
            ['id' => 2, 'orderNo' => self::orderNo(0, 11), 'invoice' => '—', 'date' => self::date(0), 'time' => '10.40', 'customerId' => 8, 'customer' => 'Andi Wijaya', 'phone' => '0852-6677-8899', 'vehicle' => 'Yamaha NMax', 'plate' => 'B 6677 TG', 'items' => 'Cuci Motor Reguler', 'serviceIds' => [4], 'total' => 20000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 0, 'payment' => '—', 'paymentStatus' => 'belum bayar', 'status' => 'menunggu', 'stampsEarned' => 1, 'crew' => 'Menunggu crew', 'bay' => '—', 'source' => 'walk-in'],
            [
                'id' => 3, 'orderNo' => self::bookingCode(0, 3), 'invoice' => self::invoiceNo(0, 10), 'date' => self::date(0), 'time' => '—', 'customerId' => 4, 'customer' => 'Maya Kusuma', 'phone' => '0857-2210-8890', 'vehicle' => 'Mitsubishi Xpander', 'plate' => 'B 4412 ZX', 'items' => 'Deep Clean Interior', 'serviceIds' => [9], 'total' => 150000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 150000, 'payment' => 'Debit', 'paymentStatus' => 'lunas', 'status' => 'booking', 'stampsEarned' => 2, 'crew' => 'Menunggu crew', 'bay' => '—', 'source' => 'booking',
                'transactions' => [
                    ['id' => self::invoiceNo(0, 10), 'orderId' => 3, 'date' => self::date(0), 'time' => '10.15', 'type' => 'Pembayaran Sebagian', 'amount' => 150000, 'channels' => 'Debit', 'channelBreakdown' => [['label' => 'Debit', 'amount' => 150000]]],
                ],
            ],
            ['id' => 4, 'orderNo' => self::orderNo(0, 9), 'invoice' => self::invoiceNo(0, 9), 'date' => self::date(0), 'time' => '09.52', 'customerId' => null, 'customer' => 'Rudi Hartono (non-member)', 'phone' => '0812-9087-6543', 'vehicle' => 'Toyota Calya', 'plate' => 'B 1290 UY', 'items' => 'Cuci Mobil Reguler', 'serviceIds' => [1], 'total' => 45000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 45000, 'payment' => 'Tunai', 'paymentStatus' => 'lunas', 'status' => 'selesai', 'stampsEarned' => 0, 'crew' => 'Agus', 'bay' => 'Bay 1', 'source' => 'walk-in'],
            [
                'id' => 5, 'orderNo' => self::bookingCode(0, 2), 'invoice' => self::invoiceNo(0, 8), 'date' => self::date(0), 'time' => '—', 'customerId' => 1, 'customer' => 'Hendra Gunawan', 'phone' => '0812-1100-2255', 'vehicle' => 'Mazda CX-5', 'plate' => 'B 5150 AB', 'items' => 'Nano Ceramic Coating', 'serviceIds' => [7], 'total' => 1500000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 1500000, 'payment' => 'Transfer', 'paymentStatus' => 'lunas', 'status' => 'booking', 'stampsEarned' => 10, 'crew' => 'Menunggu crew', 'bay' => '—', 'source' => 'booking',
                'transactions' => [
                    ['id' => self::invoiceNo(0, 8), 'orderId' => 5, 'date' => self::date(0), 'time' => '09.20', 'type' => 'Pembayaran Sebagian', 'amount' => 1500000, 'channels' => 'Transfer', 'channelBreakdown' => [['label' => 'Transfer', 'amount' => 1500000]]],
                ],
            ],
            ['id' => 6, 'orderNo' => self::orderNo(0, 7), 'invoice' => self::invoiceNo(0, 7), 'date' => self::date(0), 'time' => '08.45', 'customerId' => 5, 'customer' => 'Siti Rahmawati', 'phone' => '0821-4455-6677', 'vehicle' => 'Honda Brio', 'plate' => 'B 8821 KL', 'items' => 'Cuci Mobil Reguler, Semir Ban Premium', 'serviceIds' => [1, 12], 'total' => 45000, 'discount' => 25000, 'reward' => 'Gratis Semir Ban', 'paidAmount' => 45000, 'payment' => 'QRIS', 'paymentStatus' => 'lunas', 'status' => 'selesai', 'stampsEarned' => 1, 'crew' => 'Agus', 'bay' => 'Bay 2', 'source' => 'walk-in'],
            ['id' => 7, 'orderNo' => self::orderNo(0, 6), 'invoice' => '—', 'date' => self::date(0), 'time' => '08.05', 'customerId' => 2, 'customer' => 'Rizky Pratama', 'phone' => '0813-7788-1200', 'vehicle' => 'Honda Civic', 'plate' => 'B 9090 RS', 'items' => 'Snow Wash Premium, Engine Bay Cleaning', 'serviceIds' => [3, 8], 'total' => 210000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 0, 'payment' => '—', 'paymentStatus' => 'belum bayar', 'status' => 'proses', 'stampsEarned' => 3, 'crew' => 'Agus & Deni', 'bay' => 'Bay 2', 'source' => 'walk-in'],
            ['id' => 8, 'orderNo' => self::orderNo(0, 5), 'invoice' => self::invoiceNo(0, 5), 'date' => self::date(0), 'time' => '07.48', 'customerId' => null, 'customer' => 'Sari Wulandari (non-member)', 'phone' => '0857-4432-1098', 'vehicle' => 'Suzuki XL7', 'plate' => 'B 7742 KD', 'items' => 'Cuci Mobil + Wax', 'serviceIds' => [2], 'total' => 85000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 85000, 'payment' => 'Tunai', 'paymentStatus' => 'lunas', 'status' => 'selesai', 'stampsEarned' => 0, 'crew' => 'Deni', 'bay' => 'Bay 3', 'source' => 'walk-in'],
            ['id' => 9, 'orderNo' => self::orderNo(0, 4), 'invoice' => '—', 'date' => self::date(0), 'time' => '07.30', 'customerId' => 9, 'customer' => 'Dewi Lestari', 'phone' => '0813-2233-5566', 'vehicle' => 'Daihatsu Ayla', 'plate' => 'B 3311 QW', 'items' => 'Cuci Mobil Reguler', 'serviceIds' => [1], 'total' => 45000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 0, 'payment' => '—', 'paymentStatus' => 'belum bayar', 'status' => 'pelunasan', 'stampsEarned' => 1, 'crew' => 'Bagas', 'bay' => 'Bay 3', 'source' => 'walk-in'],
            [
                'id' => 10, 'orderNo' => self::bookingCode(0, 1), 'invoice' => '—', 'date' => self::date(0), 'time' => '07.15', 'customerId' => 3, 'customer' => 'Budi Santoso', 'phone' => '0812-3456-7890', 'vehicle' => 'Toyota Avanza', 'plate' => 'B 1234 CDE', 'items' => 'Cuci Mobil + Wax', 'serviceIds' => [2], 'total' => 85000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 55000, 'payment' => 'Transfer + QRIS + Tunai', 'paymentStatus' => 'sebagian', 'status' => 'pelunasan', 'crew' => 'Menunggu crew', 'stampsEarned' => 1, 'bay' => '—', 'source' => 'booking',
                'transactions' => [
                    ['id' => self::bookingCode(0, 1).'-TRX-1', 'orderId' => 10, 'date' => self::date(-1), 'time' => '15.30', 'type' => 'Pembayaran Sebagian', 'amount' => 20000, 'channels' => 'Transfer', 'channelBreakdown' => [['label' => 'Transfer', 'amount' => 20000]]],
                    ['id' => self::bookingCode(0, 1).'-TRX-2', 'orderId' => 10, 'date' => self::date(0), 'time' => '07.15', 'type' => 'Pembayaran Sebagian', 'amount' => 20000, 'channels' => 'QRIS + Tunai', 'channelBreakdown' => [['label' => 'QRIS', 'amount' => 15000], ['label' => 'Tunai', 'amount' => 5000]]],
                    ['id' => self::bookingCode(0, 1).'-TRX-3', 'orderId' => 10, 'date' => self::date(0), 'time' => '10.30', 'type' => 'Pembayaran Sebagian', 'amount' => 15000, 'channels' => 'Tunai', 'channelBreakdown' => [['label' => 'Tunai', 'amount' => 15000]]],
                ],
            ],
            ['id' => 11, 'orderNo' => self::bookingCode(-1, 7), 'invoice' => self::invoiceNo(-1, 7), 'date' => self::date(-1), 'time' => '09.30', 'customerId' => 9, 'customer' => 'Dewi Lestari', 'phone' => '0813-2233-5566', 'vehicle' => 'Daihatsu Ayla', 'plate' => 'B 3311 QW', 'items' => 'Cuci Mobil Reguler', 'serviceIds' => [1], 'total' => 45000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 45000, 'payment' => 'Tunai', 'paymentStatus' => 'lunas', 'status' => 'selesai', 'stampsEarned' => 1, 'crew' => 'Bagas', 'bay' => 'Bay 3', 'source' => 'booking'],
            ['id' => 12, 'orderNo' => self::bookingCode(-1, 6), 'invoice' => '—', 'date' => self::date(-1), 'time' => '14.00', 'customerId' => 11, 'customer' => 'Gilang Ramadhan', 'phone' => '0817-8899-1010', 'vehicle' => 'Wuling Almaz', 'plate' => 'B 9021 HH', 'items' => 'Snow Wash Premium', 'serviceIds' => [3], 'total' => 120000, 'discount' => 0, 'reward' => '—', 'paidAmount' => 0, 'payment' => '—', 'paymentStatus' => 'belum bayar', 'status' => 'batal', 'stampsEarned' => 0, 'crew' => '—', 'bay' => '—', 'source' => 'booking'],
        ];
    }

    /**
     * Bookings the crew is still waiting for, written as orders parked on the
     * 'booking' stage so the order board and the booking board show the same
     * job under the same number (BR-05, BR-08).
     *
     * A booking that already has a row in {@see self::recordedOrders()} — it
     * arrived, was settled, or was cancelled — is left alone; that row carries
     * where it stands.
     *
     * @return list<array{id: int, orderNo: string, invoice: string, date: string, time: string, bookingDate: string, customerId: int|null, customer: string, phone: string, vehicle: string, plate: string, items: string, serviceIds: list<int>, total: int, discount: int, reward: string, paidAmount: int, payment: string, paymentStatus: string, status: string, stampsEarned: int, crew: string, bay: string, source: string, transactions: list<array{id: string, orderId: int, date: string, time: string, type: string, amount: int, channels: string, channelBreakdown: list<array{label: string, amount: int}>}>}>
     */
    public static function bookingOrders(): array
    {
        $stampsByService = array_column(Catalog::services(), 'stamps', 'id');

        $recorded = array_column(self::recordedOrders(), 'orderNo');

        $awaitingArrival = array_filter(
            self::bookings(),
            fn (array $booking): bool => ! in_array($booking['code'], $recorded, true),
        );

        return array_values(array_map(fn (array $booking): array => [
            'id' => 900 + $booking['id'],
            'orderNo' => $booking['code'],
            'invoice' => '—',
            'date' => $booking['date'],
            'time' => '—',
            'bookingDate' => $booking['bookingDate'],
            'customerId' => $booking['customerId'],
            'customer' => $booking['customer'],
            'phone' => $booking['phone'],
            'vehicle' => $booking['vehicle'],
            'plate' => $booking['plate'],
            'items' => $booking['service'],
            'serviceIds' => $booking['serviceIds'],
            'total' => $booking['estimate'],
            'discount' => 0,
            'reward' => '—',
            'paidAmount' => 0,
            'payment' => '—',
            'paymentStatus' => 'belum bayar',
            'status' => 'booking',
            'stampsEarned' => array_sum(array_map(
                fn (int $serviceId): int => $stampsByService[$serviceId] ?? 0,
                $booking['serviceIds'],
            )),
            'crew' => 'Menunggu crew',
            'bay' => '—',
            'source' => 'booking',
            'transactions' => [],
        ], $awaitingArrival));
    }

    /** A seeded day, counted from today: 0 is today, -1 yesterday, 2 in two days. */
    private static function date(int $daysFromToday): string
    {
        return Reports::today()->addDays($daysFromToday)->toDateString();
    }

    /** The "260819" a number carries so it reads as the day it belongs to. */
    private static function dateCode(int $daysFromToday): string
    {
        return Reports::today()->addDays($daysFromToday)->format('ymd');
    }

    /** Walk-in numbering: ORD-260819 plus the sequence of the day. */
    private static function orderNo(int $daysFromToday, int $sequence): string
    {
        return 'ORD-'.self::dateCode($daysFromToday).str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    /** A booking is numbered the same way, with BK marking where it came from. */
    private static function bookingCode(int $daysFromToday, int $sequence): string
    {
        return 'ORD-BK-'.self::dateCode($daysFromToday).str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    /** Invoices repeat the order's day so a receipt files itself. */
    private static function invoiceNo(int $daysFromToday, int $sequence): string
    {
        return 'ZW-'.self::dateCode($daysFromToday).str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    /**
     * The single order lifecycle the floor works through, in order. Payment
     * state stays a separate cashier concern, so the floor only tracks where a
     * car is: booked but not arrived, waiting, being washed, handed to the
     * cashier, then done. An order that never gets washed ends on 'batal'.
     *
     * @return list<string>
     */
    public static function orderStatuses(): array
    {
        return ['booking', 'menunggu', 'proses', 'pelunasan', 'selesai', 'batal'];
    }

    /**
     * The stages the floor may set by hand from the order detail. 'selesai' is
     * left out because only the cashier closes an order once payment is
     * settled. Cancellation is selected through the same status input.
     *
     * @return list<string>
     */
    public static function editableOrderStatuses(): array
    {
        return ['booking', 'menunggu', 'proses', 'pelunasan', 'batal'];
    }

    /**
     * Orders handed to the cashier by the floor for final settlement.
     *
     * @return list<array{id: int, orderNo: string, invoice: string, date: string, time: string, bookingDate: string|null, customerId: int|null, customer: string, phone: string, vehicle: string, plate: string, items: string, serviceIds: list<int>, total: int, discount: int, reward: string, paidAmount: int, payment: string, paymentStatus: string, status: string, stampsEarned: int, crew: string, bay: string, source: string, transactions: list<array{id: string, orderId: int, date: string, time: string, type: string, amount: int, channels: string, channelBreakdown: list<array{label: string, amount: int}>}>}>
     */
    public static function settlementOrders(): array
    {
        return array_values(array_filter(
            self::orders(),
            fn (array $order): bool => $order['status'] === 'pelunasan',
        ));
    }

    /**
     * Scheduled bookings whose vehicle has not arrived yet and whose visit is
     * today or later. Once a booking reaches settlement, the cashier handles it
     * exclusively in the settlement section.
     *
     * @return list<array{id: int, orderNo: string, invoice: string, date: string, time: string, bookingDate: string|null, customerId: int|null, customer: string, phone: string, vehicle: string, plate: string, items: string, serviceIds: list<int>, total: int, discount: int, reward: string, paidAmount: int, payment: string, paymentStatus: string, status: string, stampsEarned: int, crew: string, bay: string, source: string, transactions: list<array{id: string, orderId: int, date: string, time: string, type: string, amount: int, channels: string, channelBreakdown: list<array{label: string, amount: int}>}>}>
     */
    public static function partialPaymentBookingOrders(): array
    {
        $today = Reports::todayDate();

        return array_values(array_filter(
            self::orders(),
            fn (array $order): bool => $order['source'] === 'booking'
                && $order['status'] === 'booking'
                && $order['date'] >= $today,
        ));
    }

    /**
     * Rupiah still owed across the orders that have arrived, so the cash
     * summary never drifts away from the order list. A booking is not billable
     * until the car shows up, so it is left out.
     */
    public static function outstandingTotal(): int
    {
        return array_sum(array_map(
            fn (array $order): int => $order['total'] - $order['paidAmount'],
            self::billableOrders(),
        ));
    }

    /**
     * Orders the cashier may settle: everything except the bookings still
     * waiting for their car to arrive and the orders that were cancelled.
     *
     * @return list<array{id: int, orderNo: string, invoice: string, date: string, time: string, bookingDate: string|null, customerId: int|null, customer: string, phone: string, vehicle: string, plate: string, items: string, serviceIds: list<int>, total: int, discount: int, reward: string, paidAmount: int, payment: string, paymentStatus: string, status: string, stampsEarned: int, crew: string, bay: string, source: string, transactions: list<array{id: string, orderId: int, date: string, time: string, type: string, amount: int, channels: string, channelBreakdown: list<array{label: string, amount: int}>}>}>
     */
    public static function billableOrders(): array
    {
        return array_values(array_filter(
            self::orders(),
            fn (array $order): bool => ! in_array($order['status'], ['booking', 'batal'], true),
        ));
    }

    /**
     * Orders whose vehicle has arrived and is being or has been served.
     *
     * @return list<array{id: int, orderNo: string, invoice: string, date: string, time: string, bookingDate: string|null, customerId: int|null, customer: string, phone: string, vehicle: string, plate: string, items: string, serviceIds: list<int>, total: int, discount: int, reward: string, paidAmount: int, payment: string, paymentStatus: string, status: string, stampsEarned: int, crew: string, bay: string, source: string, transactions: list<array{id: string, orderId: int, date: string, time: string, type: string, amount: int, channels: string, channelBreakdown: list<array{label: string, amount: int}>}>}>
     */
    public static function servedOrders(string $date): array
    {
        return array_values(array_filter(
            DateFilter::apply(self::orders(), $date),
            fn (array $order): bool => ! in_array($order['status'], ['booking', 'batal'], true),
        ));
    }

    /**
     * Vehicle totals for a business day, using the same rows shown on the order
     * board. Cancelled orders no longer count as served or scheduled work.
     *
     * @return array{total: int, served: int, awaitingBooking: int}
     */
    public static function orderSummary(string $date): array
    {
        $orders = array_values(array_filter(
            DateFilter::apply(self::orders(), $date),
            fn (array $order): bool => $order['status'] !== 'batal',
        ));
        $awaitingBooking = count(array_filter(
            $orders,
            fn (array $order): bool => $order['status'] === 'booking',
        ));

        return [
            'total' => count($orders),
            'served' => count(self::servedOrders($date)),
            'awaitingBooking' => $awaitingBooking,
        ];
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
     * Bookings as the booking board reads them: each one carrying the status of
     * its order, because the order module owns where a job stands.
     *
     * @return list<array{id: int, code: string, customerId: int|null, customer: string, phone: string, vehicle: string, plate: string, service: string, serviceIds: list<int>, date: string, bookingDate: string, orderStatus: string, estimate: int, notes: string}>
     */
    public static function scheduledBookings(): array
    {
        $statusByOrderNo = array_column(self::orders(), 'status', 'orderNo');

        return array_map(
            fn (array $booking): array => [
                ...$booking,
                'orderStatus' => $statusByOrderNo[$booking['code']] ?? 'booking',
            ],
            self::bookings(),
        );
    }

    /**
     * Scheduled bookings (BR-08), each one a date and a job — never a status.
     * Where a booking stands is the order module's answer, so the board reads it
     * back through {@see self::scheduledBookings()}.
     *
     * @return list<array{id: int, code: string, customerId: int|null, customer: string, phone: string, vehicle: string, plate: string, service: string, serviceIds: list<int>, date: string, bookingDate: string, estimate: int, notes: string}>
     */
    public static function bookings(): array
    {
        return [
            ['id' => 1, 'code' => self::bookingCode(0, 1), 'customerId' => 3, 'customer' => 'Budi Santoso', 'phone' => '0812-3456-7890', 'vehicle' => 'Toyota Avanza', 'plate' => 'B 1234 CDE', 'service' => 'Cuci Mobil + Wax', 'serviceIds' => [2], 'date' => self::date(0), 'bookingDate' => self::date(-1), 'estimate' => 85000, 'notes' => 'Minta lap ekstra di bagasi.'],
            ['id' => 2, 'code' => self::bookingCode(0, 2), 'customerId' => 1, 'customer' => 'Hendra Gunawan', 'phone' => '0812-1100-2255', 'vehicle' => 'Mazda CX-5', 'plate' => 'B 5150 AB', 'service' => 'Nano Ceramic Coating', 'serviceIds' => [7], 'date' => self::date(0), 'bookingDate' => self::date(0), 'estimate' => 1500000, 'notes' => 'Pembayaran sebagian 50% via transfer.'],
            ['id' => 3, 'code' => self::bookingCode(0, 3), 'customerId' => 4, 'customer' => 'Maya Kusuma', 'phone' => '0857-2210-8890', 'vehicle' => 'Mitsubishi Xpander', 'plate' => 'B 4412 ZX', 'service' => 'Deep Clean Interior', 'serviceIds' => [9], 'date' => self::date(0), 'bookingDate' => self::date(0), 'estimate' => 150000, 'notes' => 'Ada noda kopi di karpet depan.'],
            ['id' => 4, 'code' => self::bookingCode(0, 4), 'customerId' => 7, 'customer' => 'Clara Halim', 'phone' => '0811-3030-4040', 'vehicle' => 'Suzuki Ertiga', 'plate' => 'B 1717 PO', 'service' => 'Snow Wash Premium', 'serviceIds' => [3], 'date' => self::date(0), 'bookingDate' => self::date(-1), 'estimate' => 120000, 'notes' => '—'],
            ['id' => 5, 'code' => self::bookingCode(1, 1), 'customerId' => 2, 'customer' => 'Rizky Pratama', 'phone' => '0813-7788-1200', 'vehicle' => 'Honda Civic', 'plate' => 'B 9090 RS', 'service' => 'Poles Body Detailing', 'serviceIds' => [6], 'date' => self::date(1), 'bookingDate' => self::date(0), 'estimate' => 450000, 'notes' => 'Baret halus di pintu kanan.'],
            ['id' => 6, 'code' => self::bookingCode(1, 2), 'customerId' => 5, 'customer' => 'Siti Rahmawati', 'phone' => '0821-4455-6677', 'vehicle' => 'Honda Brio', 'plate' => 'B 8821 KL', 'service' => 'Salon Jok & Karpet', 'serviceIds' => [10], 'date' => self::date(1), 'bookingDate' => self::date(-1), 'estimate' => 350000, 'notes' => '—'],
            ['id' => 7, 'code' => self::bookingCode(2, 1), 'customerId' => 6, 'customer' => 'Fajar Nugroho', 'phone' => '0878-9012-3344', 'vehicle' => 'Toyota Fortuner', 'plate' => 'B 7788 JK', 'service' => 'Cuci Mobil + Wax', 'serviceIds' => [2], 'date' => self::date(2), 'bookingDate' => self::date(0), 'estimate' => 85000, 'notes' => '—'],
            ['id' => 8, 'code' => self::bookingCode(-1, 7), 'customerId' => 9, 'customer' => 'Dewi Lestari', 'phone' => '0813-2233-5566', 'vehicle' => 'Daihatsu Ayla', 'plate' => 'B 3311 QW', 'service' => 'Cuci Mobil Reguler', 'serviceIds' => [1], 'date' => self::date(-1), 'bookingDate' => self::date(-1), 'estimate' => 45000, 'notes' => '—'],
            ['id' => 9, 'code' => self::bookingCode(-1, 6), 'customerId' => 11, 'customer' => 'Gilang Ramadhan', 'phone' => '0817-8899-1010', 'vehicle' => 'Wuling Almaz', 'plate' => 'B 9021 HH', 'service' => 'Snow Wash Premium', 'serviceIds' => [3], 'date' => self::date(-1), 'bookingDate' => self::date(-2), 'estimate' => 120000, 'notes' => 'Pelanggan membatalkan via WhatsApp.'],
        ];
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
        return ['Tunai', 'QRIS', 'Kredit', 'Debit', 'Transfer', 'E-Money'];
    }
}
