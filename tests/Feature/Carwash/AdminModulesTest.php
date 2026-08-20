<?php

use App\Support\Carwash\RoleAccess;
use Inertia\Testing\AssertableInertia;

/**
 * Each admin module renders its own page with the props that page needs,
 * plus the shell props every module shares (BR-05 … BR-13).
 */
dataset('admin modules', [
    'dashboard' => ['carwash.admin.dashboard', 'carwash/admin/Dashboard', ['stats', 'filters', 'shifts', 'cashSummary', 'orderSummary']],
    'orders' => ['carwash.admin.orders', 'carwash/admin/Orders', ['orders', 'filters', 'orderStatuses', 'upcoming', 'services', 'customers', 'crew']],
    'pos' => ['carwash.admin.pos', 'carwash/admin/Pos', ['orders', 'filters', 'services', 'customers', 'rewards', 'paymentMethods']],
    'customers' => ['carwash.admin.customers', 'carwash/admin/Customers', ['customers', 'orders', 'stampHistory', 'stampTarget']],
    'finance' => ['carwash.admin.finance', 'carwash/admin/Finance', ['moneyIn', 'moneyOut', 'filters', 'incomeCategories', 'expenseCategories', 'cashSummary']],
    'bookings' => ['carwash.admin.bookings', 'carwash/admin/Bookings', ['bookings', 'today', 'services', 'customers']],
    'inventory' => ['carwash.admin.inventory', 'carwash/admin/Inventory', ['items', 'movements', 'categories', 'movementTypes']],
    'rewards' => ['carwash.admin.rewards', 'carwash/admin/Rewards', ['rewards', 'categories', 'stampTarget']],
    'users' => ['carwash.admin.users', 'carwash/admin/Users', ['staff', 'roles', 'matrix', 'allModules']],
    'reports' => ['carwash.admin.reports', 'carwash/admin/Reports', ['stats', 'trend', 'filters', 'customerActivity', 'bookingSummary', 'inventorySummary']],
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

test('the sidebar only offers modules the active role may reach', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'cs'])
        ->get(route('carwash.admin.customers'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('role.key', 'cs')
                ->has('modules', 2)
                // Sidebar order is preserved from RoleAccess::modules().
                ->where('modules.0.key', 'orders')
                ->where('modules.1.key', 'customers')
        );
});

test('the entry screen exposes the roles and the full access matrix', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('carwash/auth/Entry')
                ->has('roles', 5)
                ->has('modules', 10)
                ->has('matrix')
        );
});

test('the cashier persona is shown in the console shell', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'cashier'])
        ->get(route('carwash.admin.pos'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('persona.name', 'Yuni Astuti')
                ->where('role.name', 'Kasir')
        );
});

test('every service in the catalog carries a stamp value and price', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->get(route('carwash.admin.pos'))
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
        ->get(route('carwash.admin.finance'))
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
