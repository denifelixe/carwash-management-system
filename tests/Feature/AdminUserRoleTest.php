<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\AdminShift;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

function rolePermissions(bool $read = true): array
{
    return AdminModule::query()->orderBy('sort_order')->get()->map(
        fn (AdminModule $module): array => [
            'module_id' => $module->id,
            'can_create' => $read && $module->key !== 'dashboard',
            'can_read' => $read,
            'can_update' => $read && $module->key !== 'dashboard',
            'can_delete' => false,
        ],
    )->all();
}

test('guests cannot open the live user and role module', function () {
    $this->get(route('admin.users.index'))
        ->assertRedirect(route('admin.login'));
});

test('an owner sees live staff roles shifts permissions and an enabled sidebar item', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Users')
                ->where('mode', 'live')
                ->has('staff', 1)
                ->has('roles', 4)
                ->has('roleIcons', 14)
                ->where('roles.0.icon', '🎧')
                ->where('roles.1.icon', '🧾')
                ->where('roles.2.icon', '💳')
                ->where('roles.3.icon', '🧑‍💼')
                ->has('shifts', 2)
                ->has('allModules', 16)
                ->where('capabilities.create', true)
                ->where('capabilities.update', true)
                ->where('modules.10.key', 'users_and_roles')
                ->where('modules.10.enabled', true)
                ->where('modules.10.active', true)
                ->where('modules.10.href', route('admin.users.index', absolute: false))
        );
});

test('hidden admins are absent from the staff directory and role counts', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $cashierRole = AdminRole::query()->where('key', 'cashier')->firstOrFail();
    $visibleCashier = Admin::factory()->create(['role_id' => $cashierRole->id]);
    $hiddenCashier = Admin::factory()->create([
        'role_id' => $cashierRole->id,
        'is_hidden' => true,
    ]);
    $hiddenOwner = Admin::factory()->create([
        'is_owner' => true,
        'is_hidden' => true,
    ]);

    $response = $this->actingAs($owner, 'admin')->get(route('admin.users.index'));
    $staff = collect($response->inertiaProps('staff'));
    $cashier = collect($response->inertiaProps('roles'))->firstWhere('id', $cashierRole->id);

    $response->assertOk();

    expect($staff->pluck('id'))
        ->toContain($owner->id, $visibleCashier->id)
        ->not->toContain($hiddenCashier->id, $hiddenOwner->id)
        ->and($cashier['staff_count'])->toBe(1)
        ->and($response->inertiaProps('ownerSummary.staff_count'))->toBe(1);
});

test('a staff role icon is exposed to the admin shell', function () {
    $usersModule = AdminModule::query()->where('key', 'users_and_roles')->firstOrFail();
    $role = AdminRole::query()->create([
        'key' => 'supervisor_icon',
        'name' => 'Supervisor Icon',
        'description' => 'Supervisor with a selected icon.',
        'icon' => '👔',
        'is_active' => true,
    ]);
    $role->modules()->attach($usersModule, ['can_read' => true]);
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('role.name', 'Supervisor Icon')
            ->where('role.icon', '👔'));
});

test('an owner can create a staff user with a role and shift', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $role = AdminRole::query()->where('key', 'cashier')->firstOrFail();
    $shift = AdminShift::query()->where('key', 'morning')->firstOrFail();

    $this->actingAs($owner, 'admin')
        ->post(route('admin.users.store'), [
            'name' => 'Yuni Astuti',
            'email' => 'yuni@example.com',
            'phone' => '081399112233',
            'role_id' => $role->id,
            'shift_id' => $shift->id,
            'password' => '!',
            'password_confirmation' => '!',
            'is_active' => true,
            'is_hidden' => true,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    $admin = Admin::query()->where('email', 'yuni@example.com')->firstOrFail();

    expect($admin)
        ->role_id->toBe($role->id)
        ->shift_id->toBe($shift->id)
        ->is_owner->toBeFalse()
        ->is_active->toBeTrue()
        ->is_hidden->toBeFalse()
        ->and(Hash::check('!', $admin->password))->toBeTrue();
});

test('an owner can replace a staff password with one unrestricted character', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $role = AdminRole::query()->where('key', 'manager')->firstOrFail();
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'role_id' => $role->id,
            'shift_id' => null,
            'password' => '*',
            'password_confirmation' => '*',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    expect(Hash::check('*', $admin->refresh()->password))->toBeTrue();
});

test('an owner can update staff without replacing an unchanged password', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $role = AdminRole::query()->where('key', 'manager')->firstOrFail();
    $admin = Admin::factory()->create(['role_id' => $role->id]);
    $password = $admin->password;

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.users.update', $admin), [
            'name' => 'Manager Baru',
            'email' => $admin->email,
            'phone' => '081200000001',
            'role_id' => $role->id,
            'shift_id' => null,
            'password' => '',
            'password_confirmation' => '',
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    expect($admin->refresh())
        ->name->toBe('Manager Baru')
        ->phone->toBe('081200000001')
        ->is_active->toBeFalse()
        ->password->toBe($password);
});

test('an owner can update a staff profile photo', function () {
    $disk = (string) config('filesystems.default');
    Storage::fake($disk);
    $owner = Admin::factory()->create(['is_owner' => true]);
    $role = AdminRole::query()->where('key', 'manager')->firstOrFail();
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.users.update', $admin), [
            '_method' => 'patch',
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'role_id' => $role->id,
            'shift_id' => null,
            'password' => '',
            'password_confirmation' => '',
            'is_active' => true,
            'photo' => UploadedFile::fake()->image('pegawai.png'),
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    $photoPath = $admin->refresh()->profile_photo_path;

    expect($photoPath)->not->toBeNull();
    Storage::disk($disk)->assertExists($photoPath);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.users.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('capabilities.update_photo', true)
                ->where('staff', fn (Collection $staff): bool => $staff
                    ->firstWhere('id', $admin->id)['avatar'] === $admin->profilePhotoUrl())
        );
});

test('a non-owner cannot update another staff profile photo', function () {
    Storage::fake((string) config('filesystems.default'));
    $usersModule = AdminModule::query()->where('key', 'users_and_roles')->firstOrFail();
    $managerRole = AdminRole::query()->create([
        'key' => 'photo_manager',
        'name' => 'Photo Manager',
        'description' => 'May update staff details.',
        'is_active' => true,
    ]);
    $managerRole->modules()->attach($usersModule, [
        'can_read' => true,
        'can_update' => true,
    ]);
    $cashierRole = AdminRole::query()->where('key', 'cashier')->firstOrFail();
    $manager = Admin::factory()->create(['role_id' => $managerRole->id]);
    $admin = Admin::factory()->create(['role_id' => $cashierRole->id]);

    $this->actingAs($manager, 'admin')
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'role_id' => $cashierRole->id,
            'shift_id' => null,
            'password' => '',
            'password_confirmation' => '',
            'is_active' => true,
            'photo' => UploadedFile::fake()->image('pegawai.png'),
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors('photo');

    expect($admin->refresh()->profile_photo_path)->toBeNull();
});

test('last active time is displayed in Indonesian', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $admin = Admin::factory()->create(['last_login_at' => now()->subHours(2)]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.users.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('staff', fn (Collection $staff): bool => $staff
                    ->firstWhere('id', $admin->id)['last_active'] === '2 jam yang lalu')
        );
});

test('an owner can assign a shift directly to any user including their own account', function () {
    $owner = Admin::factory()->create(['is_owner' => true, 'shift_id' => null]);
    $shift = AdminShift::query()->where('key', 'morning')->firstOrFail();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.users.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('staff.0.shift_name', 'Tidak ada Shift')
        );

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.users.shift.update', $owner), [
            'shift_id' => $shift->id,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    expect($owner->refresh()->shift_id)->toBe($shift->id);
});

test('an owner can make a user follow the current shift schedule', function () {
    $owner = Admin::factory()->create(['is_owner' => true, 'shift_id' => null]);
    $shift = AdminShift::query()->where('key', 'morning')->firstOrFail();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.users.shift.update', $owner), [
            'shift_mode' => 'schedule',
            'shift_id' => null,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    expect($owner->refresh())
        ->shift_mode->toBe('schedule')
        ->shift_id->toBeNull();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.users.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('staff.0.shift_mode', 'schedule')
            ->where('staff.0.shift_name', 'Mengikuti Jam Shift'));

    $this->actingAs($owner, 'admin')
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.shift.update', $owner), [
            'shift_mode' => 'schedule',
            'shift_id' => $shift->id,
        ])
        ->assertSessionHasErrors('shift_id');
});

test('the owner account cannot be changed from the staff module', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $role = AdminRole::query()->where('key', 'manager')->firstOrFail();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.users.update', $owner), [
            'name' => 'Changed Owner',
            'email' => $owner->email,
            'phone' => null,
            'role_id' => $role->id,
            'shift_id' => null,
            'password' => '',
            'password_confirmation' => '',
            'is_active' => false,
        ])
        ->assertForbidden();

    expect($owner->refresh()->name)->not->toBe('Changed Owner');
});

test('an owner can create and update a role permission matrix', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $permissions = rolePermissions();

    $this->actingAs($owner, 'admin')
        ->post(route('admin.roles.store'), [
            'name' => 'Supervisor Operasional',
            'description' => 'Mengawasi operasional harian.',
            'icon' => '👔',
            'is_active' => true,
            'permissions' => $permissions,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    $role = AdminRole::query()->where('key', 'supervisor_operasional')->firstOrFail();

    expect($role->modules()->wherePivot('can_read', true)->count())->toBe(16)
        ->and($role->icon)->toBe('👔');

    $permissions[1]['can_read'] = false;

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.roles.update', $role), [
            'name' => 'Supervisor',
            'description' => 'Supervisor yang diperbarui.',
            'icon' => '🏆',
            'is_active' => false,
            'permissions' => $permissions,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    expect($role->refresh())
        ->name->toBe('Supervisor')
        ->icon->toBe('🏆')
        ->is_active->toBeFalse()
        ->and($role->modules()->wherePivot('can_read', true)->count())->toBe(15);
});

test('a role icon must come from the role icon options', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->from(route('admin.users.index'))
        ->post(route('admin.roles.store'), [
            'name' => 'Invalid Icon Role',
            'description' => null,
            'icon' => '😀',
            'is_active' => true,
            'permissions' => rolePermissions(),
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors('icon');

    expect(AdminRole::query()->where('name', 'Invalid Icon Role')->exists())->toBeFalse();
});

test('read-only staff can view the module but cannot mutate users', function () {
    $module = AdminModule::query()->where('key', 'users_and_roles')->firstOrFail();
    $role = AdminRole::query()->create([
        'key' => 'auditor',
        'name' => 'Auditor',
        'description' => 'Read only.',
        'is_active' => true,
    ]);
    $role->modules()->attach($module, ['can_read' => true]);
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('capabilities.create', false)
                ->where('capabilities.update', false)
        );

    $this->actingAs($admin, 'admin')
        ->post(route('admin.users.store'), [])
        ->assertForbidden();

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.users.shift.update', $admin), ['shift_id' => null])
        ->assertForbidden();
});

test('an inactive role grants no module access', function () {
    $module = AdminModule::query()->where('key', 'users_and_roles')->firstOrFail();
    $role = AdminRole::query()->create([
        'key' => 'inactive_auditor',
        'name' => 'Inactive Auditor',
        'description' => 'Disabled role.',
        'is_active' => false,
    ]);
    $role->modules()->attach($module, ['can_read' => true]);
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.users.index'))
        ->assertForbidden();
});
