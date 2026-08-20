<?php

use App\Support\Carwash\RoleAccess;
use Inertia\Testing\AssertableInertia;

test('the page heading names the module without dating it', function () {
    $layout = file_get_contents(
        resource_path('js/layouts/carwash/AdminLayout.vue'),
    );

    expect($layout)
        ->toContain('{{ activeModule.label }}')
        ->not->toContain('{{ brand.today }}');
});

test('the finance module is named keuangan throughout the console', function () {
    $financeModule = collect(RoleAccess::modules())->firstWhere('key', 'finance');
    $financePage = file_get_contents(
        resource_path('js/pages/carwash/admin/Finance.vue'),
    );

    expect($financeModule['label'])->toBe('Keuangan')
        ->and($financePage)->toContain('`${brand.name} — Keuangan`');
});

test('the customer module is named member throughout the console', function () {
    $customerModule = collect(RoleAccess::modules())->firstWhere('key', 'customers');
    $customerPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Customers.vue'),
    );

    expect($customerModule['label'])->toBe('Member')
        ->and($customerPage)->toContain('`${brand.name} — Member`');
});

test('the sidebar modules follow the operational menu order', function () {
    expect(array_column(RoleAccess::modules(), 'key'))->toBe([
        'dashboard',
        'bookings',
        'orders',
        'pos',
        'finance',
        'customers',
        'inventory',
        'rewards',
        'users',
        'reports',
    ]);
});

test('the dashboard only presents daily operational summaries', function () {
    $dashboard = file_get_contents(
        resource_path('js/pages/carwash/admin/Dashboard.vue'),
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
        ->get(route('carwash.admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('carwash/admin/Dashboard')
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
        resource_path('js/pages/carwash/admin/Dashboard.vue'),
    );

    expect($dashboard)
        ->toContain('Kendaraan dilayani')
        ->toContain('{{ shift.vehiclesServed }}')
        ->toContain('{{ formatCurrency(shift.revenue) }}')
        ->not->toContain('{{ formatShortCurrency(shift.revenue) }}')
        ->not->toContain('{{ shift.transactions }}');
});
