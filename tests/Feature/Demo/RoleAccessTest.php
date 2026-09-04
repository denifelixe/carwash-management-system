<?php

use App\Support\Admin\AdminModuleActions;
use App\Support\Demo\RoleAccess;
use Inertia\Testing\AssertableInertia;

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
    $this->get(route('demo.admin.dashboard'))
        ->assertRedirect(route('demo.home'));
});

test('an unknown session role is treated as no role at all', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'janitor'])
        ->get(route('demo.admin.dashboard'))
        ->assertRedirect(route('demo.home'));
});

test('choosing a role stores it and lands on that role first module', function () {
    $this->post(route('demo.session.role'), ['role' => 'cashier'])
        ->assertRedirect(route('demo.admin.pos'));

    expect(session(RoleAccess::SESSION_KEY))->toBe('cashier');
});

test('an invalid role cannot be selected', function () {
    $this->post(route('demo.session.role'), ['role' => 'janitor'])
        ->assertSessionHasErrors('role');

    expect(session(RoleAccess::SESSION_KEY))->toBeNull();
});

test('leaving the console clears the active role', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->post(route('demo.session.exit'))
        ->assertRedirect(route('demo.home'));

    expect(session(RoleAccess::SESSION_KEY))->toBeNull();
});

test('the owner is the only role that can manage users', function () {
    $ownersOfUserModule = array_keys(array_filter(
        RoleAccess::matrix(),
        fn (array $modules): bool => in_array('users', $modules, true),
    ));

    expect($ownersOfUserModule)->toBe(['owner']);
});

test('only the manager staff role receives finance additional actions by default', function () {
    foreach (AdminModuleActions::for('finance') as $action) {
        expect(RoleAccess::allowsAdditionalAction('manager', 'finance', $action['key']))
            ->toBeTrue()
            ->and(RoleAccess::allowsAdditionalAction('finance', 'finance', $action['key']))
            ->toBeFalse()
            ->and(RoleAccess::allowsAdditionalAction('cashier', 'finance', $action['key']))
            ->toBeFalse();
    }
});

test('every staff member has an editable shift and the active persona identifies the same staff member', function () {
    expect(RoleAccess::shifts())->toBe(['Shift Pagi', 'Shift Sore']);

    foreach (RoleAccess::staff() as $staff) {
        expect($staff['shift'])->toBeIn(RoleAccess::shifts());
    }

    $owner = RoleAccess::staff()[0];
    $ownerPersona = RoleAccess::personaFor()['owner'];

    expect($ownerPersona['id'])->toBe($owner['id'])
        ->and($ownerPersona['name'])->toBe($owner['name'])
        ->and($ownerPersona['shift'])->toBe($owner['shift']);
});

test('demo and live user management share one interactive vue page', function () {
    $usersPage = file_get_contents(
        resource_path('js/pages/admin/Users.vue'),
    );
    $adminLayout = file_get_contents(
        resource_path('js/layouts/admin/AdminLayout.vue'),
    );

    expect($usersPage)
        ->toContain("props.mode === 'demo'")
        ->toContain('saveDemoUser()')
        ->toContain('saveDemoRole()')
        ->toContain('@change="changeShift(person, $event)"')
        ->toContain('updateUserShift(person.id)')
        ->and(resource_path('js/pages/demo/admin/Users.vue'))->not->toBeFile()
        ->and($adminLayout)
        ->toContain('{{ role.name }}')
        ->not->toContain('{{ role.name }} - {{ persona.shift }}')
        ->not->toContain('{{ modules.length }} modul');
});

test('demo and live member management share one interactive vue page', function () {
    $memberPage = file_get_contents(
        resource_path('js/pages/admin/Customers.vue'),
    );

    expect($memberPage)
        ->toContain("props.mode === 'demo'")
        ->toContain('saveDemoMember()')
        ->toContain('saveLiveMember()')
        ->toContain('updateMemberStatus(customer.id)')
        ->and(resource_path('js/pages/demo/admin/Customers.vue'))->not->toBeFile();
});

test('the demo user module supplies the live page contract from dummy data', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->get(route('demo.admin.users'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Users')
                ->where('mode', 'demo')
                ->has('staff', 8)
                ->has('roles', 4)
                ->has('roles.0.permissions', 15)
                ->has('shifts', 2)
                ->has('allModules', 15)
                ->where('allModules.9.key', 'users_and_roles')
                ->where('capabilities.create', true)
                ->where('capabilities.update', true)
        );
});

test('every module in the matrix maps to a real registered route', function () {
    foreach (RoleAccess::modules() as $module) {
        expect(fn () => route($module['route']))->not->toThrow(Exception::class);
    }
});
