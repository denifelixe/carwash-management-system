<?php

use App\Support\Demo\RoleAccess;
use Inertia\Testing\AssertableInertia;

/**
 * Each admin module renders its own page with the props that page needs,
 * plus the shell props every module shares (BR-05 … BR-13).
 */
dataset('admin modules', [
    'dashboard' => ['demo.admin.dashboard', 'admin/Dashboard', ['stats', 'filters', 'shifts', 'cashSummary', 'orderSummary']],
    'orders' => ['demo.admin.orders', 'admin/Orders', ['orders', 'filters', 'orderStatuses', 'upcoming', 'services', 'customers', 'crew']],
    'pos' => ['demo.admin.pos', 'admin/Pos', ['orders', 'filters', 'services', 'customers', 'rewards', 'paymentMethods']],
    'members' => ['demo.admin.members', 'admin/Customers', ['members', 'stats', 'filters', 'stampTarget', 'capabilities']],
    'finance' => ['demo.admin.finance', 'admin/Finance', ['moneyIn', 'moneyOut', 'filters', 'incomeCategories', 'expenseCategories', 'cashSummary', 'orders']],
    'bookings' => ['demo.admin.bookings', 'admin/Bookings', ['bookings', 'today', 'services', 'customers', 'capabilities']],
    'inventory' => ['demo.admin.inventory', 'demo/admin/Inventory', ['items', 'movements', 'categories', 'movementTypes']],
    'rewards' => ['demo.admin.rewards', 'demo/admin/Rewards', ['rewards', 'categories', 'stampTarget']],
    'users' => ['demo.admin.users', 'admin/Users', ['staff', 'roles', 'shifts', 'ownerSummary', 'capabilities', 'allModules']],
    'reports' => ['demo.admin.reports', 'demo/admin/Reports', ['stats', 'trend', 'filters', 'customerActivity', 'bookingSummary', 'inventorySummary']],
    'master services' => ['demo.admin.master.services', 'admin/master/Services', ['services', 'categories', 'capabilities']],
    'master work shifts' => ['demo.admin.master.work-shifts', 'admin/master/WorkShifts', ['workShifts', 'capabilities']],
]);

test('an owner can open every module with its expected props', function (string $routeName, string $component, array $props) {
    $this->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($component, $props) {
            $page->component($component)
                ->has('brand')
                ->has('role')
                ->has('modules')
                ->has('persona');

            foreach ($props as $prop) {
                $page->has($prop);
            }
        });
})->with('admin modules');

test('the master module is nested under an expandable sidebar group', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->get(route('demo.admin.master.services'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('modules.10.key', 'master')
                ->where('modules.10.href', null)
                ->where('modules.10.active', true)
                ->where('modules.10.children.0.key', 'master_services')
                ->where('modules.10.children.0.href', route('demo.admin.master.services', absolute: false))
        );
});

test('the sidebar only offers modules the active role may reach', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'cs'])
        ->get(route('demo.admin.members'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('role.key', 'cs')
                ->has('modules', 2)
                // Sidebar order is preserved from RoleAccess::modules().
                ->where('modules.0.key', 'orders')
                ->where('modules.1.key', 'members')
        );
});

test('the entry screen exposes the roles and the full access matrix', function () {
    $this->get(route('demo.home'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('demo/auth/Entry')
                ->has('roles', 5)
                ->has('modules', 12)
                ->has('matrix')
        );
});

test('the cashier persona is shown in the console shell', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'cashier'])
        ->get(route('demo.admin.pos'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('persona.name', 'Yuni Astuti')
                ->where('persona.shift', 'Shift Pagi')
                ->where('role.name', 'Kasir')
        );
});

test('every service in the catalog carries a stamp value and price', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->get(route('demo.admin.pos'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            /** @var list<array{price: int, stamps: int}> $services */
            $services = $page->toArray()['props']['services'];

            expect($services)->not->toBeEmpty();

            foreach ($services as $service) {
                expect($service['price'])->toBeGreaterThan(0)
                    ->and($service['stamps'])->toBeGreaterThanOrEqual(0);
            }
        });
});

test('every recorded expense carries a supporting attachment', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'finance'])
        ->get(route('demo.admin.finance'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            /** @var list<array{attachment: array{name: string}|null}> $expenses */
            $expenses = $page->toArray()['props']['moneyOut'];

            expect($expenses)->not->toBeEmpty();

            foreach ($expenses as $expense) {
                expect($expense['attachment'])->not->toBeNull()
                    ->and($expense['attachment']['name'])->not->toBeEmpty();
            }
        });
});
