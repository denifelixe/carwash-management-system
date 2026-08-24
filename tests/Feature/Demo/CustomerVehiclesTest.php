<?php

use App\Support\Demo\Customers;
use App\Support\Demo\RoleAccess;
use Inertia\Testing\AssertableInertia;

test('a customer can own multiple vehicles with different plates', function () {
    $customer = collect(Customers::all())->firstWhere('id', 1);

    expect($customer)->not->toBeNull()
        ->and($customer['vehicles'])->toHaveCount(2)
        ->and(array_column($customer['vehicles'], 'plate'))->toContain('B 5150 AB', 'B 2020 HG')
        ->and($customer['vehicles'][0]['isPrimary'])->toBeTrue()
        ->and($customer['vehicles'][1]['isPrimary'])->toBeFalse();
});

test('the customer page receives every vehicle owned by a customer', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->get(route('demo.admin.customers'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('demo/admin/Customers')
                ->has('customers.0.vehicles', 2)
                ->where('customers.0.vehicles.0.plate', 'B 5150 AB')
                ->where('customers.0.vehicles.1.plate', 'B 2020 HG')
        );
});
