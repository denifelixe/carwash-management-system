<?php

use App\Models\Admin;
use App\Support\Demo\RoleAccess;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

test('admin and role schema contains the required tables columns and indexes', function () {
    expect(Schema::hasTable('admin_roles'))->toBeTrue()
        ->and(Schema::hasTable('admin_shifts'))->toBeTrue()
        ->and(Schema::hasTable('admin_work_shifts'))->toBeFalse()
        ->and(Schema::hasTable('admin_modules'))->toBeTrue()
        ->and(Schema::hasTable('admin_role_module'))->toBeTrue()
        ->and(Schema::hasTable('members'))->toBeTrue()
        ->and(Schema::hasTable('admin_password_reset_tokens'))->toBeTrue()
        ->and(Schema::hasTable('member_password_reset_tokens'))->toBeTrue()
        ->and(Schema::hasTable('passkeys'))->toBeFalse()
        ->and(Schema::hasColumn('admins', 'two_factor_secret'))->toBeFalse()
        ->and(Schema::hasColumn('admins', 'two_factor_recovery_codes'))->toBeFalse()
        ->and(Schema::hasColumn('admins', 'two_factor_confirmed_at'))->toBeFalse()
        ->and(Schema::hasColumn('admins', 'work_shift_id'))->toBeFalse()
        ->and(Schema::hasColumns('admin_roles', [
            'id',
            'key',
            'name',
            'description',
            'is_active',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('admin_shifts', [
            'id',
            'key',
            'name',
            'starts_at',
            'ends_at',
            'is_active',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('admin_modules', [
            'id',
            'key',
            'name',
            'description',
            'sort_order',
            'is_active',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('admin_role_module', [
            'admin_role_id',
            'admin_module_id',
            'can_create',
            'can_read',
            'can_update',
            'can_delete',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('admins', [
            'id',
            'role_id',
            'shift_id',
            'name',
            'email',
            'phone',
            'email_verified_at',
            'password',
            'is_owner',
            'is_active',
            'is_hidden',
            'last_login_at',
            'remember_token',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('members', [
            'id',
            'name',
            'email',
            'phone',
            'email_verified_at',
            'password',
            'is_active',
            'last_login_at',
            'remember_token',
            'created_at',
            'updated_at',
        ]))->toBeTrue();

    $hasUniqueIndex = fn (string $table, array $columns): bool => collect(Schema::getIndexes($table))
        ->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === $columns);

    $shiftIndexNames = collect(Schema::getIndexes('admin_shifts'))->pluck('name');

    expect($hasUniqueIndex('admin_roles', ['key']))->toBeTrue()
        ->and($hasUniqueIndex('admin_shifts', ['key']))->toBeTrue()
        ->and($shiftIndexNames)->toContain('admin_shifts_key_unique', 'admin_shifts_is_active_index')
        ->not->toContain('admin_work_shifts_key_unique', 'admin_work_shifts_is_active_index')
        ->and($hasUniqueIndex('admin_modules', ['key']))->toBeTrue()
        ->and($hasUniqueIndex('admin_role_module', ['admin_role_id', 'admin_module_id']))->toBeTrue()
        ->and($hasUniqueIndex('admins', ['phone']))->toBeTrue()
        ->and($hasUniqueIndex('members', ['email']))->toBeTrue()
        ->and($hasUniqueIndex('members', ['phone']))->toBeTrue();
});

test('admin modules are prefilled from the demo navigation', function () {
    $modules = DB::table('admin_modules')
        ->orderBy('sort_order')
        ->get(['key', 'name', 'description', 'sort_order']);

    /* The demo navigation, plus the master modules that only exist live. */
    expect($modules)->toHaveCount(14);
    expect($modules->last()->key)->toBe('master_app_settings');

    foreach (RoleAccess::modules() as $index => $demoModule) {
        $expectedKey = $demoModule['key'] === 'users'
            ? 'users_and_roles'
            : $demoModule['key'];

        expect($modules[$index]->key)->toBe($expectedKey)
            ->and($modules[$index]->name)->toBe($demoModule['label'])
            ->and($modules[$index]->description)->toBe($demoModule['caption'])
            ->and($modules[$index]->sort_order)->toBe($index + 1);
    }
});

test('owners receive full gate access while staff follow normal authorization', function () {
    Gate::define('restricted-admin-action', fn (Admin $admin): bool => false);

    $owner = Admin::factory()->create(['is_owner' => true]);
    $staff = Admin::factory()->create(['is_owner' => false]);

    expect(Gate::forUser($owner)->allows('restricted-admin-action'))->toBeTrue()
        ->and(Gate::forUser($staff)->allows('restricted-admin-action'))->toBeFalse();
});

test('the initial owner admin is created without a known password', function () {
    $owner = Admin::query()
        ->where('email', 'deni.victoria@gmail.com')
        ->first();

    expect($owner)
        ->not->toBeNull()
        ->name->toBe('Deni Victoria')
        ->is_owner->toBeTrue()
        ->is_active->toBeTrue()
        ->is_hidden->toBeTrue()
        ->role_id->toBeNull()
        ->and($owner->password)->not->toBeEmpty();
});

test('staff roles shifts modules and access assignments can be persisted', function () {
    $now = now();
    $roleId = DB::table('admin_roles')->insertGetId([
        'key' => 'owner',
        'name' => 'Owner',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $shiftId = DB::table('admin_shifts')->insertGetId([
        'key' => 'test_morning',
        'name' => 'Shift Pagi',
        'starts_at' => '08:00:00',
        'ends_at' => '16:00:00',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $moduleId = DB::table('admin_modules')->insertGetId([
        'key' => 'admins',
        'name' => 'Admin & Role',
        'sort_order' => 9,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $adminId = DB::table('admins')->insertGetId([
        'role_id' => $roleId,
        'name' => 'Admin Staff',
        'email' => 'admin@example.com',
        'phone' => '081234567890',
        'password' => 'hashed-password',
        'is_owner' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('admin_role_module')->insert([
        'admin_role_id' => $roleId,
        'admin_module_id' => $moduleId,
        'can_create' => true,
        'can_read' => true,
        'can_update' => false,
        'can_delete' => false,
    ]);

    $access = DB::table('admin_role_module')->where([
        'admin_role_id' => $roleId,
        'admin_module_id' => $moduleId,
    ])->first();

    expect(DB::table('admins')->find($adminId))
        ->role_id->toBe($roleId)
        ->phone->toBe('081234567890')
        ->is_owner->toBe(1)
        ->is_active->toBe(1)
        ->and(DB::table('admin_shifts')->find($shiftId))->not->toBeNull()
        ->and($access)->not->toBeNull()
        ->and($access->can_create)->toBe(1)
        ->and($access->can_read)->toBe(1)
        ->and($access->can_update)->toBe(0)
        ->and($access->can_delete)->toBe(0);
});

test('a module can only be assigned to a role once', function () {
    $now = now();
    $roleId = DB::table('admin_roles')->insertGetId([
        'key' => 'test_cashier',
        'name' => 'Kasir',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $moduleId = DB::table('admin_modules')->insertGetId([
        'key' => 'test-pos',
        'name' => 'Kasir POS',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $assignment = [
        'admin_role_id' => $roleId,
        'admin_module_id' => $moduleId,
    ];

    DB::table('admin_role_module')->insert($assignment);

    $access = DB::table('admin_role_module')->where($assignment)->first();

    expect($access)
        ->not->toBeNull()
        ->and($access->can_create)->toBe(0)
        ->and($access->can_read)->toBe(0)
        ->and($access->can_update)->toBe(0)
        ->and($access->can_delete)->toBe(0);

    expect(fn () => DB::table('admin_role_module')->insert($assignment))
        ->toThrow(QueryException::class);
});

test('foreign key deletion rules preserve staff integrity', function () {
    $now = now();
    $roleId = DB::table('admin_roles')->insertGetId([
        'key' => 'test_manager',
        'name' => 'Manager',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $moduleId = DB::table('admin_modules')->insertGetId([
        'key' => 'test-reports',
        'name' => 'Laporan',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $adminId = DB::table('admins')->insertGetId([
        'role_id' => $roleId,
        'name' => 'Manager Staff',
        'email' => 'manager@example.com',
        'password' => 'hashed-password',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('admin_role_module')->insert([
        'admin_role_id' => $roleId,
        'admin_module_id' => $moduleId,
    ]);

    expect(fn () => DB::table('admin_roles')->where('id', $roleId)->delete())
        ->toThrow(QueryException::class);

    DB::table('admin_modules')->where('id', $moduleId)->delete();

    expect(DB::table('admins')->find($adminId))->not->toBeNull()
        ->and(DB::table('admin_role_module')->where('admin_role_id', $roleId)->exists())->toBeFalse();
});
