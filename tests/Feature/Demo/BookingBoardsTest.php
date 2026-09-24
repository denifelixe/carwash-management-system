<?php

use App\Support\Demo\Operations;
use App\Support\Demo\Reports;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia;

test('the booking board keeps only the two schedule counters', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/admin/Bookings.vue'),
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
        resource_path('js/pages/admin/Bookings.vue'),
    );

    expect($bookingsPage)
        ->not->toContain('Jadwal mendatang')
        // Every row names the order it belongs to.
        ->toContain('{{ booking.code }}')
        ->toContain("title: 'Booking hari ini',")
        ->toContain("title: 'Booking mendatang',")
        ->toContain("title: 'Booking sebelumnya',")
        ->toContain("'Booking yang sudah lewat jadwalnya akan tampil di sini.'");

    // The order of the boards on the page: today, upcoming, then what is past.
    expect(mb_strpos($bookingsPage, "key: 'today',"))
        ->toBeLessThan(mb_strpos($bookingsPage, "key: 'upcoming',"));
    expect(mb_strpos($bookingsPage, "key: 'upcoming',"))
        ->toBeLessThan(mb_strpos($bookingsPage, "key: 'past',"));
});

test('the boards replace the booking history table', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/admin/Bookings.vue'),
    );

    expect($bookingsPage)
        ->not->toContain('Riwayat booking')
        ->not->toContain('filteredBookings')
        // One search box above the boards narrows all three (MoM 17 Sep 2026).
        ->toContain('v-model:search="bookingSearch"')
        // Creating a booking survives the table it used to sit on.
        ->toContain('Buat Booking')
        ->toContain('@click="openCreateBooking"');
});

test('booking board rows omit prices like the order list', function () {
    expect(file_get_contents(resource_path('js/pages/admin/Bookings.vue')))
        ->not->toContain('formatCurrency(booking.estimate)')
        ->toContain('<StatusPill :status="bookingPill(booking)" />');
});

test('booking rows and details follow the order information hierarchy', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/admin/Bookings.vue'),
    );

    $bookingRow = mb_substr(
        $bookingsPage,
        mb_strpos($bookingsPage, 'v-for="booking in board.bookings"'),
        mb_strpos($bookingsPage, '@click="detailBookingId = booking.id"')
            - mb_strpos($bookingsPage, 'v-for="booking in board.bookings"'),
    );
    $bookingDetail = mb_substr(
        $bookingsPage,
        mb_strpos($bookingsPage, '<div v-if="detailBooking" class="space-y-5">'),
        mb_strpos($bookingsPage, '<template #footer>')
            - mb_strpos($bookingsPage, '<div v-if="detailBooking" class="space-y-5">'),
    );

    foreach ([
        '{{ formatPlate(booking.plate) }}',
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
                '{{ formatPlate(booking.plate) }}',
                '{{ booking.vehicle }}',
                '{{ booking.customer }}',
                '{{ bookingCustomerType(booking) }}',
                '{{ booking.phone }}',
                '{{ booking.service }}',
            ][$index - 1]),
        );
    }

    expect($bookingDetail)
        ->toContain('{{ formatPlate(detailBooking.plate) }}')
        ->toContain('{{ detailBooking.vehicle }}')
        ->toContain('{{ detailBooking.customer }}')
        ->toContain('{{ bookingCustomerType(detailBooking) }}')
        ->toContain('{{ detailBooking.phone }}')
        ->toContain('{{ detailBooking.service }}');

    expect(mb_strpos($bookingDetail, '{{ formatPlate(detailBooking.plate) }}'))
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

/*
 * Board cards are compact (MoM 17 Sep 2026): plate and vehicle share one title
 * line. The detail panel keeps the order list's large plate and vehicle.
 */
test('booking cards are compact while the detail keeps the order list sizes', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/admin/Bookings.vue'),
    );
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );

    expect($bookingsPage)
        // One card per row, desktop included.
        ->toContain('class="mt-4 space-y-2"')
        ->not->toContain('lg:grid-cols-2')
        // On a phone the service keeps a full line of its own, never squeezed to nothing.
        ->toContain('w-full text-xs text-slate-500 sm:w-auto sm:min-w-0 sm:flex-1 sm:truncate')
        ->toContain('class="text-lg font-bold tracking-wide whitespace-nowrap text-slate-900"')
        ->and(substr_count($bookingsPage, 'text-2xl font-bold tracking-wide'))
        ->toBe(1)
        ->and(
            substr_count($bookingsPage, 'mt-0.5 text-xl font-semibold text-slate-700'),
        )
        ->toBe(1)
        ->and($ordersPage)
        ->toContain('mt-0.5 text-xl font-semibold text-slate-700');
});

test('booking details separate the booking date from execution and show payment history', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/admin/Bookings.vue'),
    );

    expect($bookingsPage)
        ->toContain('Waktu Input')
        ->toContain('{{ formatDate(detailBooking.bookingDate) }}')
        ->toContain('{{ detailBooking.bookingTime }}')
        ->toContain('Booking untuk')
        ->toContain('{{ formatDate(detailBooking.date) }}')
        ->toContain(':caption="detailBookingCaption"')
        ->toContain('Waktu Input: ${formatDate(booking.bookingDate)} • ${booking.bookingTime}')
        ->toContain('Booking untuk: ${formatDate(booking.date)}')
        ->toContain('].join(\'\n\');')
        ->not->toContain('Catatan')
        ->not->toContain('{{ detailBooking.notes }}')
        ->not->toContain('Estimasi biaya')
        ->toContain('formatCurrency(detailBooking.estimate)')
        ->toContain('formatCurrency(detailBooking.paidAmount)')
        ->toMatch('/detailBooking\.estimate\s*-\s*detailBooking\.paidAmount/')
        ->toContain('v-for="transaction in detailBooking.transactions"')
        ->toContain('{{ transaction.id }}')
        ->toContain('transaction.channelBreakdown')
        ->toContain('transaction.recordedBy')
        ->toContain('Belum ada transaksi');

    foreach (Operations::scheduledBookings() as $booking) {
        expect($booking)->toHaveKeys(['paidAmount', 'transactions']);
    }

    foreach (Operations::bookings() as $booking) {
        expect($booking)->toHaveKeys(['bookingDate', 'bookingTime', 'date']);
    }

    expect(Operations::bookings()[0]['bookingDate'])
        ->not->toBe(Operations::bookings()[0]['date']);
});

test('booking details remain editable while only paid services are locked', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/admin/Bookings.vue'),
    );

    expect($bookingsPage)
        ->not->toContain('daysFromToday(detailBooking.value.date) < 0')
        ->not->toContain('detailBooking.value.canEditServices === true')
        ->not->toContain("detailBooking.value.orderStatus === 'booking'")
        ->toContain('props.capabilities.update &&')
        ->toContain('detailBooking.value?.isMutable !== false')
        ->toContain('editingBooking.value?.canEditServices !== false')
        ->toContain('v-if="canEditDraftServices"')
        ->toContain('v-if="canEditDetailBooking"')
        ->toContain('@click="startEditingBooking"')
        ->toContain('Edit Booking')
        ->toContain('!canEditDetailBooking.value')
        ->toContain('draft.value.date === editingBooking.value.date')
        ->toContain("? 'Simpan booking'")
        ->toContain(": 'Simpan perubahan'");
});

test('the slide-over footer lays out its actions at full width', function () {
    expect(file_get_contents(resource_path('js/components/demo/SlideOver.vue')))
        ->toContain('sticky bottom-0 flex gap-2');
});

test('the slide-over caption keeps the line breaks it is given', function () {
    expect(file_get_contents(resource_path('js/components/demo/SlideOver.vue')))
        ->toContain('whitespace-pre-line');
});

test('the booking module never sets a status of its own', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/admin/Bookings.vue'),
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
    expect(file_get_contents(resource_path('js/pages/admin/Bookings.vue')))
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
        expect($booking['code'])->toMatch('/^\d{8}\/ORD\/BK\/\d{4}$/');
    }

    expect(file_get_contents(resource_path('js/pages/admin/Bookings.vue')))
        ->toContain('code: `${String(sequence).padStart(8,');
});

test('the booking module schedules a day, never an hour', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/admin/Bookings.vue'),
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

test('the booking date acts as one calendar button instead of an editable field', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/admin/Bookings.vue'),
    );

    expect($bookingsPage)
        ->toContain('ref="bookingDateInput"')
        ->toContain('@click="openBookingDatePicker"')
        ->toContain("typeof input.showPicker === 'function'")
        ->toContain('aria-haspopup="dialog"')
        ->toContain('cursor-pointer')
        ->toContain('select-none')
        ->toContain('pointer-events-none')
        ->toContain('tabindex="-1"')
        ->toContain('{{ displayBookingDate }}');
});

test('the booking form is the order form plus a date', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/admin/Bookings.vue'),
    );
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );

    // Both forms keep the customer picker and variation-aware service cart.
    foreach (['<ServiceCartPicker', 'v-model="draft.serviceItems"', 'class="customer-search"'] as $shared) {
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
        ->toContain('editingBooking.date < today')
        ->toContain('hasBookableDate.value &&')
        ->toContain('Tanggal kedatangan tidak boleh sebelum hari ini.')
        ->and($ordersPage)->not->toContain('Tanggal kedatangan');
});

test('the booking form starts at the actual current date', function () {
    /* An evening UTC instant that is already tomorrow on the outlet clock. */
    $this->travelTo(CarbonImmutable::parse('2026-08-18 18:00', 'UTC'));

    $this->withSession(['carwash_role' => 'owner'])
        ->get(route('demo.admin.bookings'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Bookings')
                ->where('today', '2026-08-19')
        );
});

test('a new booking is saved on the date that was picked', function () {
    expect(file_get_contents(resource_path('js/pages/admin/Bookings.vue')))
        ->toContain('date: draft.value.date,')
        ->toContain('bookingDate: props.today,')
        ->toContain("orderStatus: 'booking',");
});

test('one search narrows today, upcoming and past bookings alike', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const { ref, computed } = require('vue');
const source = fs.readFileSync('resources/js/pages/admin/Bookings.vue', 'utf8').split('<script setup lang="ts">')[1].split('</script>')[0];
const ast = ts.createSourceFile('Bookings.ts', source, ts.ScriptTarget.Latest, true);
const code = ts.transpile(ast.statements
    .filter(node => (ts.isVariableStatement(node) && ['bookingSearch', 'isSearchingBookings'].includes(node.declarationList.declarations[0].name.getText(ast)))
        || (ts.isFunctionDeclaration(node) && ['matchesBookingSearch', 'searchedBookings', 'boardBadge'].includes(node.name?.text)))
    .map(node => node.getText(ast))
    .join('\n'), { target: ts.ScriptTarget.ES2020 });
const normalizePlate = value => value.replace(/\s+/g, '').toUpperCase();
const formatPlate = value => value.replace(/^([A-Z]{1,2})(\d{1,4})([A-Z]{0,3})$/, '$1 $2 $3').trim();
const booking = (id, plate, customer, phone, code) => ({ id, plate, customer, phone, code, vehicle: 'Civic', service: 'Express Wash' });
const today = [booking(1, 'B8120DS', 'Deni', '081234', 'BK-001')];
const upcoming = [booking(2, 'A1234', 'Putri', '089999', 'BK-002')];
const past = [booking(3, 'B8120DS', 'Deni', '081234', 'BK-003'), booking(4, 'D55', 'Rizki', '087777', 'BK-004')];
const ids = list => searchedBookings(list).map(item => item.id);
eval(code + `
assert.deepEqual([ids(today), ids(upcoming), ids(past)], [[1], [2], [3, 4]]);
assert.equal(boardBadge(past, past, 'riwayat'), '2 riwayat');
for (const [query, expected] of [
    ['b 8120', [[1], [], [3]]],
    ['b8120ds', [[1], [], [3]]],
    ['putri', [[], [2], []]],
    ['0877', [[], [], [4]]],
    ['bk-003', [[], [], [3]]],
    ['civic express', [[1], [2], [3, 4]]],
    ['tidak ada', [[], [], []]],
]) {
    bookingSearch.value = query;
    assert.deepEqual([ids(today), ids(upcoming), ids(past)], expected, query);
}
bookingSearch.value = 'deni';
assert.equal(boardBadge(searchedBookings(past), past, 'riwayat'), '1 dari 2 riwayat');
`);
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});
