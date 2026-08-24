<?php

use App\Support\Demo\Operations;
use App\Support\Demo\Reports;
use Inertia\Testing\AssertableInertia;

test('the booking board keeps only the two schedule counters', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/demo/admin/Bookings.vue'),
    );

    expect($bookingsPage)
        ->toContain('label="Booking hari ini"')
        ->toContain('label="Booking mendatang"')
        ->not->toContain('label="Nilai terjadwal"')
        ->not->toContain('label="Selesai"')
        ->not->toContain('label="Total mendatang"');
});

test('the booking board stacks today, upcoming, and finished schedules', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/demo/admin/Bookings.vue'),
    );

    expect($bookingsPage)
        ->not->toContain('Jadwal mendatang')
        // Every row names the order it belongs to.
        ->toContain('{{ booking.code }}')
        ->toContain("title: 'Booking hari ini',")
        ->toContain("title: 'Booking mendatang',")
        ->toContain("title: 'Booking selesai / batal',");

    // The order of the boards on the page: today, upcoming, then what is past.
    expect(mb_strpos($bookingsPage, "key: 'today',"))
        ->toBeLessThan(mb_strpos($bookingsPage, "key: 'upcoming',"));
    expect(mb_strpos($bookingsPage, "key: 'upcoming',"))
        ->toBeLessThan(mb_strpos($bookingsPage, "key: 'past',"));
});

test('the boards replace the booking history table', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/demo/admin/Bookings.vue'),
    );

    expect($bookingsPage)
        ->not->toContain('Riwayat booking')
        ->not->toContain('filteredBookings')
        ->not->toContain('DataToolbar')
        // Creating a booking survives the table it used to sit on.
        ->toContain('Buat Booking')
        ->toContain('@click="openCreateBooking"');
});

test('booking board rows omit prices like the order list', function () {
    expect(file_get_contents(resource_path('js/pages/demo/admin/Bookings.vue')))
        ->not->toContain('formatCurrency(booking.estimate)')
        ->toContain('<StatusPill :status="bookingPill(booking)" />');
});

test('booking rows and details follow the order information hierarchy', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/demo/admin/Bookings.vue'),
    );

    $bookingRow = mb_substr(
        $bookingsPage,
        mb_strpos($bookingsPage, 'v-for="booking in board.bookings"'),
        mb_strpos($bookingsPage, '<StatusPill :status="bookingPill(booking)"')
            - mb_strpos($bookingsPage, 'v-for="booking in board.bookings"'),
    );
    $bookingDetail = mb_substr(
        $bookingsPage,
        mb_strpos($bookingsPage, '<div v-if="detailBooking" class="space-y-5">'),
        mb_strpos($bookingsPage, '<template #footer>')
            - mb_strpos($bookingsPage, '<div v-if="detailBooking" class="space-y-5">'),
    );

    foreach ([
        '{{ booking.plate }}',
        '{{ booking.vehicle }}',
        '{{ booking.customer }}',
        '{{ bookingCustomerType(booking) }}',
        '{{ booking.phone }}',
        '{{ booking.service }}',
    ] as $index => $field) {
        if ($index === 0) {
            continue;
        }

        expect(mb_strpos($bookingRow, $field))->toBeGreaterThan(
            mb_strpos($bookingRow, [
                '{{ booking.plate }}',
                '{{ booking.vehicle }}',
                '{{ booking.customer }}',
                '{{ bookingCustomerType(booking) }}',
                '{{ booking.phone }}',
                '{{ booking.service }}',
            ][$index - 1]),
        );
    }

    expect($bookingDetail)
        ->toContain('{{ detailBooking.plate }}')
        ->toContain('{{ detailBooking.vehicle }}')
        ->toContain('{{ detailBooking.customer }}')
        ->toContain('{{ bookingCustomerType(detailBooking) }}')
        ->toContain('{{ detailBooking.phone }}')
        ->toContain('{{ detailBooking.service }}');

    expect(mb_strpos($bookingDetail, '{{ detailBooking.plate }}'))
        ->toBeLessThan(mb_strpos($bookingDetail, '{{ detailBooking.vehicle }}'));
    expect(mb_strpos($bookingDetail, '{{ detailBooking.vehicle }}'))
        ->toBeLessThan(mb_strpos($bookingDetail, '{{ detailBooking.customer }}'));
    expect(mb_strpos($bookingDetail, '{{ detailBooking.customer }}'))
        ->toBeLessThan(mb_strpos($bookingDetail, '{{ detailBooking.phone }}'));
    expect(mb_strpos($bookingDetail, '{{ detailBooking.phone }}'))
        ->toBeLessThan(mb_strpos($bookingDetail, '{{ detailBooking.service }}'));

    expect($bookingsPage)
        ->toContain("return booking.customerId === null ? 'Non-Member' : 'Member';");
});

test('booking details separate the booking date from execution without showing a price', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/demo/admin/Bookings.vue'),
    );

    expect($bookingsPage)
        ->toContain('Tanggal Booking')
        ->toContain('{{ formatDate(detailBooking.bookingDate) }}')
        ->toContain('Tanggal Order')
        ->toContain('{{ formatDate(detailBooking.date) }}')
        ->not->toContain('Catatan')
        ->not->toContain('{{ detailBooking.notes }}')
        ->not->toContain('Estimasi biaya')
        ->not->toContain('formatCurrency(detailBooking.estimate)');

    foreach (Operations::bookings() as $booking) {
        expect($booking)->toHaveKeys(['bookingDate', 'date']);
    }

    expect(Operations::bookings()[0]['bookingDate'])
        ->not->toBe(Operations::bookings()[0]['date']);
});

test('only bookings that have not entered order processing can be edited', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/demo/admin/Bookings.vue'),
    );

    expect($bookingsPage)
        ->toContain("detailBooking.value?.orderStatus === 'booking'")
        ->toContain('v-if="canEditDetailBooking"')
        ->toContain('@click="startEditingBooking"')
        ->toContain('Edit Booking')
        ->toContain("booking.orderStatus !== 'booking'")
        ->toContain("? 'Simpan booking'")
        ->toContain(": 'Simpan perubahan'");
});

test('the slide-over footer lays out its actions at full width', function () {
    expect(file_get_contents(resource_path('js/components/demo/SlideOver.vue')))
        ->toContain('sticky bottom-0 flex gap-2');
});

test('the booking module never sets a status of its own', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/demo/admin/Bookings.vue'),
    );

    // No status writing, and none of the buttons that used to do it.
    expect($bookingsPage)
        ->not->toContain('function setStatus(')
        ->not->toContain('Mulai kerjakan')
        ->not->toContain('Tandai selesai')
        ->not->toContain('Batalkan');

    foreach (Operations::bookings() as $booking) {
        expect($booking)->not->toHaveKey('status');
    }
});

test('today and passed bookings show their order status while upcoming bookings show their schedule', function () {
    expect(file_get_contents(resource_path('js/pages/demo/admin/Bookings.vue')))
        ->toContain('if (daysAhead <= 0) {')
        ->toContain('return booking.orderStatus;')
        ->toContain("return 'mendatang';")
        ->toContain(':status="bookingPill(booking)"');

    $today = Reports::todayDate();
    $todayBookings = array_values(array_filter(
        Operations::scheduledBookings(),
        fn (array $booking): bool => $booking['date'] === $today,
    ));

    expect(array_column($todayBookings, 'orderStatus'))
        ->toContain('booking')
        ->toContain('pelunasan');

    // A booking whose day has passed can only have ended one of two ways.
    foreach (Operations::scheduledBookings() as $booking) {
        if ($booking['date'] >= Reports::todayDate()) {
            continue;
        }

        expect($booking['orderStatus'])->toBeIn(['selesai', 'batal']);
    }
});

test('the day markers have their own pill tones', function () {
    expect(file_get_contents(resource_path('js/components/demo/StatusPill.vue')))
        ->toContain("case 'mendatang':")
        ->toContain("case 'hari ini':");
});

test('booking numbers reuse the order numbering with a BK marker', function () {
    foreach (Operations::bookings() as $booking) {
        expect($booking['code'])->toStartWith('ORD-BK-');
    }

    expect(file_get_contents(resource_path('js/pages/demo/admin/Bookings.vue')))
        ->toContain('code: `ORD-BK-${formatDateCode(draft.value.date)}${String(sequence).padStart(2,');
});

test('the booking module schedules a day, never an hour', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/demo/admin/Bookings.vue'),
    );

    expect($bookingsPage)
        ->not->toContain('{{ booking.time }}')
        ->not->toContain('Jam kedatangan')
        ->toContain('{{ dayLabelFor(booking.date) }}');

    foreach (Operations::bookings() as $booking) {
        expect($booking)->not->toHaveKey('time')
            ->and($booking)->toHaveKey('date');
    }
});

test('the booking form is the order form plus a date', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/demo/admin/Bookings.vue'),
    );
    $ordersPage = file_get_contents(
        resource_path('js/pages/demo/admin/Orders.vue'),
    );

    // Both forms keep the customer picker and multi-service grid.
    foreach ([
        'function toggleDraftService(serviceId: number): void {',
        'class="customer-search"',
    ] as $shared) {
        expect($ordersPage)->toContain($shared)
            ->and($bookingsPage)->toContain($shared);
    }

    expect($ordersPage)
        ->toContain("{ key: 'existing', label: 'Member' },")
        ->toContain("{ key: 'walk-in', label: 'Non-Member' },")
        ->not->toContain("{ key: 'new-member', label: 'Member baru' },");

    // Plus the one field an order has no use for.
    expect($bookingsPage)
        ->toContain('Tanggal kedatangan')
        ->toContain('id="booking-date"')
        ->toContain(':min="today"')
        ->toContain('hasBookableDate.value &&')
        ->toContain('Tanggal kedatangan tidak boleh sebelum hari ini.')
        ->and($ordersPage)->not->toContain('Tanggal kedatangan');
});

test('the booking form starts at the actual current date', function () {
    $this->travelTo('2026-08-18 18:00:00');

    $this->withSession(['carwash_role' => 'owner'])
        ->get(route('demo.admin.bookings'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('demo/admin/Bookings')
                ->where('today', '2026-08-19')
        );
});

test('a new booking is saved on the date that was picked', function () {
    expect(file_get_contents(resource_path('js/pages/demo/admin/Bookings.vue')))
        ->toContain('date: draft.value.date,')
        ->toContain('bookingDate: props.today,')
        ->toContain("orderStatus: 'booking',");
});
