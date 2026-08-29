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
        ->get(route('demo.admin.members'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Customers')
                ->where('mode', 'demo')
                ->has('members.meta')
                ->has('members.data.0.vehicles', 2)
                ->where('members.data.0.vehicles.0.plate', 'B 5150 AB')
                ->where('members.data.0.vehicles.1.plate', 'B 2020 HG')
                ->where('capabilities.create', true)
        );
});

test('the demo member page combines status and portal account filters', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->get(route('demo.admin.members', [
            'status' => 'tidak aktif',
            'account' => 'Punya akun portal',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('filters.status', 'tidak aktif')
                ->where('filters.account', 'Punya akun portal')
                ->where('members.meta.total', 1)
                ->where('members.data.0.id', 12),
        );
});
