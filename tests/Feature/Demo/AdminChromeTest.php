<?php

use App\Support\Demo\Reports;
use App\Support\Demo\RoleAccess;
use Inertia\Testing\AssertableInertia;

test('the page heading names the module without dating it', function () {
    $layout = file_get_contents(
        resource_path('js/layouts/admin/AdminLayout.vue'),
    );

    expect($layout)
        ->toContain('{{ pageTitle }}')
        ->toContain('page.props.pageTitle ?? activeModule.value.label')
        ->not->toContain('{{ brand.today }}');
});

test('the finance module is named keuangan throughout the console', function () {
    $financeModule = collect(RoleAccess::modules())->firstWhere('key', 'finance');
    $financePage = file_get_contents(
        resource_path('js/pages/admin/Finance.vue'),
    );

    expect($financeModule['label'])->toBe('Keuangan')
        ->and($financePage)->toContain('`${brand.name} — Keuangan`');
});

test('the customer module is named member throughout the console', function () {
    $customerModule = collect(RoleAccess::modules())->firstWhere('key', 'members');
    $customerPage = file_get_contents(
        resource_path('js/pages/admin/Customers.vue'),
    );

    expect($customerModule['label'])->toBe('Member')
        ->and($customerPage)->toContain('`${brand.name} — Member`')
        ->toContain('title="Daftarkan member"')
        ->toContain('Daftar Member')
        ->toContain('Simpan member')
        ->toContain(':for="`member-vehicle-${index}-plate`"')
        ->toContain(':for="`member-vehicle-${index}-name`"')
        ->toContain(':for="`member-vehicle-${index}-type`"')
        ->toContain('text-sm text-slate-900 focus:border-cyan-400')
        ->toContain('Tidak punya akun portal')
        ->toContain('toggleStatusFilter')
        ->toContain('toggleAccountFilter')
        ->toContain('allFiltersSelected')
        ->toContain("detailCustomer.email || 'Tidak ada email'")
        ->not->toContain('title="Daftarkan customer"')
        ->not->toContain('Simpan customer');
});

test('the sidebar modules follow the operational menu order', function () {
    expect(array_column(RoleAccess::modules(), 'key'))->toBe([
        'dashboard',
        'orders',
        'pos',
        'bookings',
        'finance',
        'members',
        'inventory',
        'rewards',
        'reports',
        'users',
        'master_services',
        'master_work_shifts',
    ]);
});

test('the dashboard only presents daily operational summaries', function () {
    $dashboard = file_get_contents(
        resource_path('js/pages/admin/Dashboard.vue'),
    );

    expect($dashboard)
        ->not->toContain('Antrean hari ini')
        ->not->toContain('Performa crew')
        ->not->toContain('v-for="person in crew"')
        ->not->toContain('Semua bay beroperasi normal')
        ->not->toContain('Buka Kasir')
        ->not->toContain('Kelola Customer')
        ->not->toContain('Omzet minggu ini')
        ->not->toContain('Pendapatan 7 hari terakhir')
        ->not->toContain('Layanan terlaris')
        ->toContain('Total order kendaraan hari ini')
        ->toContain('orderSummary.total')
        ->toContain('orderSummary.served')
        ->toContain('dilayani,')
        ->toContain('orderSummary.awaitingBooking')
        ->toContain('booking -')
        ->toContain('belum datang');
});

test('the dashboard is no longer handed the crew list', function () {
    $this->withSession(['carwash_role' => 'owner'])
        ->get(route('demo.admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->has('orderSummary')
                ->missing('queue')
                ->missing('crew')
                ->missing('revenueTrend')
                ->missing('topServices')
                ->missing('customerCount')
        );
});

test('the shift summary shows served vehicles instead of payment transactions', function () {
    $dashboard = file_get_contents(
        resource_path('js/pages/admin/Dashboard.vue'),
    );

    expect($dashboard)
        ->toContain('Kendaraan dilayani')
        ->toContain('{{ shift.vehiclesServed }}')
        ->toContain('{{ formatCurrency(shift.revenue) }}')
        ->not->toContain('{{ formatShortCurrency(shift.revenue) }}')
        ->not->toContain('{{ shift.transactions }}');
});

test('the dashboard uses finance and member metrics with the daily remaining balance', function () {
    $dashboard = file_get_contents(
        resource_path('js/pages/admin/Dashboard.vue'),
    );
    $stats = Reports::dashboardStats(Reports::todayDate());

    expect($stats[0]['caption'])->toContain('transaksi keuangan')
        ->and($stats[2]['label'])->toBe('Member Aktif')
        ->and($stats[3]['label'])->toBe('Stempel Ditukar')
        ->and($dashboard)
        ->toContain('Sisa Saldo')
        ->toContain('cashSummary.remainingBalance')
        ->not->toContain('Saldo awal')
        ->not->toContain('cashSummary.openingBalance')
        ->not->toContain('Saldo akhir')
        ->not->toContain('cashSummary.closingBalance')
        ->not->toContain('Customer Aktif');
});

test('the summary cards collapse on tablets exactly like on phones', function () {
    $summary = file_get_contents(
        resource_path('js/components/demo/CollapsibleSummary.vue'),
    );
    $layout = file_get_contents(
        resource_path('js/layouts/admin/AdminLayout.vue'),
    );

    expect($summary)
        ->toContain("isAlwaysCollapsible ? '' : 'lg:hidden'")
        ->toContain("isOpen.value ? 'mt-3 grid lg:mt-0' : 'hidden lg:grid'")
        ->not->toContain("'sm:hidden'")
        ->not->toContain('hidden sm:grid')
        // The toggle survives right up to the width where the shell trades its
        // hamburger for the fixed sidebar.
        ->and($layout)
        ->toContain('text-slate-600 lg:hidden');
});

test('the order overview relies on the default collapsible summary', function () {
    $ordersPage = file_get_contents(
        resource_path('js/pages/admin/Orders.vue'),
    );

    expect($ordersPage)
        ->toContain('<CollapsibleSummary')
        ->toContain('title="Ringkasan hari ini"')
        ->not->toContain('collapsible="never"')
        ->not->toContain('collapsible="always"');
});
