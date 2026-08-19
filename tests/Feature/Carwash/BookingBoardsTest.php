<?php

use App\Support\Carwash\Operations;
use App\Support\Carwash\Reports;
use Inertia\Testing\AssertableInertia;

test('the booking board keeps only the two schedule counters', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Bookings.vue'),
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
        resource_path('js/pages/carwash/admin/Bookings.vue'),
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
        resource_path('js/pages/carwash/admin/Bookings.vue'),
    );

    expect($bookingsPage)
        ->not->toContain('Riwayat booking')
        ->not->toContain('filteredBookings')
        ->not->toContain('DataToolbar')
        // Creating a booking survives the table it used to sit on.
        ->toContain('Buat Booking')
        ->toContain('@click="isCreateOpen = true"');
});

test('the booking module never sets a status of its own', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Bookings.vue'),
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

test('today and mendatang only mark the day, while a passed booking reads the order', function () {
    expect(file_get_contents(resource_path('js/pages/carwash/admin/Bookings.vue')))
        ->toContain('return booking.orderStatus;')
        ->toContain("return daysAhead === 0 ? 'hari ini' : 'mendatang';")
        ->toContain(':status="bookingPill(booking)"');

    // A booking whose day has passed can only have ended one of two ways.
    foreach (Operations::scheduledBookings() as $booking) {
        if ($booking['date'] >= Reports::todayDate()) {
            continue;
        }

        expect($booking['orderStatus'])->toBeIn(['selesai', 'batal']);
    }
});

test('the day markers have their own pill tones', function () {
    expect(file_get_contents(resource_path('js/components/carwash/StatusPill.vue')))
        ->toContain("case 'mendatang':")
        ->toContain("case 'hari ini':");
});

test('booking numbers reuse the order numbering with a BK marker', function () {
    foreach (Operations::bookings() as $booking) {
        expect($booking['code'])->toStartWith('ORD-BK-');
    }

    expect(file_get_contents(resource_path('js/pages/carwash/admin/Bookings.vue')))
        ->toContain('code: `ORD-BK-${formatDateCode(draft.value.date)}${String(sequence).padStart(2,');
});

test('the booking module schedules a day, never an hour', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Bookings.vue'),
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
        resource_path('js/pages/carwash/admin/Bookings.vue'),
    );
    $ordersPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Orders.vue'),
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
        ->get(route('carwash.admin.bookings'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('carwash/admin/Bookings')
                ->where('today', '2026-08-19')
        );
});

test('a new booking is saved on the date that was picked', function () {
    expect(file_get_contents(resource_path('js/pages/carwash/admin/Bookings.vue')))
        ->toContain('date: draft.value.date,')
        ->toContain("orderStatus: 'booking',");
});
