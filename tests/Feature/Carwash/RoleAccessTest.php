<?php

use App\Support\Carwash\RoleAccess;

/**
 * Walks every role × module pair so the matrix and the middleware can never
 * drift apart unnoticed (BR-11).
 */
dataset('role module pairs', function () {
    foreach (array_keys(RoleAccess::matrix()) as $role) {
        foreach (RoleAccess::modules() as $module) {
            yield "{$role} → {$module['key']}" => [
                $role,
                $module['route'],
                RoleAccess::allows($role, $module['key']),
            ];
        }
    }
});

test('each role reaches exactly the modules its matrix allows', function (string $role, string $routeName, bool $allowed) {
    $this->withSession([RoleAccess::SESSION_KEY => $role]);

    $response = $this->get(route($routeName));

    $allowed
        ? $response->assertOk()
        : $response->assertForbidden();
})->with('role module pairs');

test('visitors without a demo role are sent to the entry screen', function () {
    $this->get(route('carwash.admin.dashboard'))
        ->assertRedirect(route('home'));
});

test('an unknown session role is treated as no role at all', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'janitor'])
        ->get(route('carwash.admin.dashboard'))
        ->assertRedirect(route('home'));
});

test('choosing a role stores it and lands on that role first module', function () {
    $this->post(route('carwash.session.role'), ['role' => 'cashier'])
        ->assertRedirect(route('carwash.admin.pos'));

    expect(session(RoleAccess::SESSION_KEY))->toBe('cashier');
});

test('an invalid role cannot be selected', function () {
    $this->post(route('carwash.session.role'), ['role' => 'janitor'])
        ->assertSessionHasErrors('role');

    expect(session(RoleAccess::SESSION_KEY))->toBeNull();
});

test('leaving the console clears the active role', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->post(route('carwash.session.exit'))
        ->assertRedirect(route('home'));

    expect(session(RoleAccess::SESSION_KEY))->toBeNull();
});

test('the owner is the only role that can manage users', function () {
    $ownersOfUserModule = array_keys(array_filter(
        RoleAccess::matrix(),
        fn (array $modules): bool => in_array('users', $modules, true),
    ));

    expect($ownersOfUserModule)->toBe(['owner']);
});

test('every module in the matrix maps to a real registered route', function () {
    foreach (RoleAccess::modules() as $module) {
        expect(fn () => route($module['route']))->not->toThrow(Exception::class);
    }
});
