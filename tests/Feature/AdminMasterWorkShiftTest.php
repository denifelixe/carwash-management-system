<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\AdminWorkShift;
use Inertia\Testing\AssertableInertia;

/**
 * @param  array<string, bool>  $abilities
 */
function workShiftStaff(array $abilities): Admin
{
    $role = AdminRole::query()->create([
        'key' => 'shift_'.uniqid(),
        'name' => 'Shift Staff',
        'description' => 'Role uji akses master shift.',
        'is_active' => true,
    ]);

    $role->modules()->attach(
        AdminModule::query()->where('key', 'master_work_shifts')->firstOrFail(),
        [
            'can_create' => $abilities['create'] ?? false,
            'can_read' => $abilities['read'] ?? false,
            'can_update' => $abilities['update'] ?? false,
            'can_delete' => $abilities['delete'] ?? false,
        ],
    );

    return Admin::factory()->create(['role_id' => $role->id]);
}

/** @return array<string, mixed> */
function workShiftPayload(array $overrides = []): array
{
    return array_merge([
        'key' => 'night',
        'name' => 'Shift Malam',
        'starts_at' => '23:00',
        'ends_at' => '07:00',
        'is_active' => true,
    ], $overrides);
}

test('guests cannot open the master work shift module', function () {
    $this->get(route('admin.master.work-shifts.index'))
        ->assertRedirect(route('admin.login'));
});

test('an owner sees work shifts in the expandable master sidebar group', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.master.work-shifts.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/master/WorkShifts')
                ->where('mode', 'live')
                ->has('workShifts', 2)
                ->where('workShifts.0.starts_at', '08:00')
                ->where('capabilities.create', true)
                ->where('capabilities.update', true)
                ->where('capabilities.delete', true)
                ->where('modules.10.key', 'master')
                ->where('modules.10.active', true)
                ->where('modules.10.children.1.key', 'master_work_shifts')
                ->where('modules.10.children.1.active', true)
                ->where('modules.10.children.1.href', route('admin.master.work-shifts.index', absolute: false)),
        );
});

test('an owner can create an overnight work shift', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.work-shifts.store'), workShiftPayload())
        ->assertRedirect(route('admin.master.work-shifts.index'))
        ->assertSessionHasNoErrors();

    $workShift = AdminWorkShift::query()->where('key', 'night')->firstOrFail();

    expect($workShift)
        ->name->toBe('Shift Malam')
        ->starts_at->toBe('23:00')
        ->ends_at->toBe('07:00')
        ->is_active->toBeTrue();
});

test('an owner can create a work shift without work hours', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.work-shifts.store'), workShiftPayload([
            'key' => 'flexible',
            'name' => 'Shift Fleksibel',
            'starts_at' => null,
            'ends_at' => null,
        ]))
        ->assertRedirect(route('admin.master.work-shifts.index'))
        ->assertSessionHasNoErrors();

    $workShift = AdminWorkShift::query()->where('key', 'flexible')->firstOrFail();

    expect($workShift)
        ->starts_at->toBeNull()
        ->ends_at->toBeNull();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.master.work-shifts.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('workShifts.0.key', 'flexible')
                ->where('workShifts.0.starts_at', null)
                ->where('workShifts.0.ends_at', null),
        );
});

test('work shift hours must either both be filled or both be empty', function (string $missingField) {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.work-shifts.store'), workShiftPayload([
            $missingField => null,
        ]))
        ->assertSessionHasErrors($missingField);
})->with(['starts_at', 'ends_at']);

test('an owner can update and deactivate a work shift', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $workShift = AdminWorkShift::query()->where('key', 'morning')->firstOrFail();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.master.work-shifts.update', $workShift), workShiftPayload([
            'key' => 'early',
            'name' => 'Shift Awal',
            'starts_at' => '06:00',
            'ends_at' => '14:00',
            'is_active' => false,
        ]))
        ->assertRedirect(route('admin.master.work-shifts.index'))
        ->assertSessionHasNoErrors();

    expect($workShift->refresh())
        ->key->toBe('early')
        ->name->toBe('Shift Awal')
        ->starts_at->toBe('06:00')
        ->ends_at->toBe('14:00')
        ->is_active->toBeFalse();
});

test('work shift keys names and time ranges are validated', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.master.work-shifts.store'), workShiftPayload([
            'key' => 'morning',
            'name' => 'Shift Pagi',
            'ends_at' => '23:00',
        ]))
        ->assertSessionHasErrors(['key', 'name', 'ends_at']);

    expect(AdminWorkShift::query()->where('key', 'night')->exists())->toBeFalse();
});

test('an owner can delete an unused work shift', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $workShift = AdminWorkShift::query()->create(workShiftPayload());

    $this->actingAs($owner, 'admin')
        ->delete(route('admin.master.work-shifts.destroy', $workShift))
        ->assertRedirect(route('admin.master.work-shifts.index'))
        ->assertSessionHasNoErrors();

    expect(AdminWorkShift::query()->find($workShift->id))->toBeNull();
});

test('a work shift assigned to an admin cannot be deleted', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $workShift = AdminWorkShift::query()->where('key', 'morning')->firstOrFail();
    Admin::factory()->create(['work_shift_id' => $workShift->id]);

    $this->actingAs($owner, 'admin')
        ->from(route('admin.master.work-shifts.index'))
        ->delete(route('admin.master.work-shifts.destroy', $workShift))
        ->assertRedirect(route('admin.master.work-shifts.index'))
        ->assertSessionHasErrors('work_shift');

    expect(AdminWorkShift::query()->find($workShift->id))->not->toBeNull();
});

test('staff access follows master work shift capabilities', function () {
    $readOnlyStaff = workShiftStaff(['read' => true]);
    $blockedStaff = workShiftStaff(['read' => false]);
    $workShift = AdminWorkShift::query()->where('key', 'morning')->firstOrFail();

    $this->actingAs($blockedStaff, 'admin')
        ->get(route('admin.master.work-shifts.index'))
        ->assertForbidden();

    $this->actingAs($readOnlyStaff, 'admin')
        ->get(route('admin.master.work-shifts.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('capabilities.create', false)
                ->where('capabilities.update', false)
                ->where('capabilities.delete', false),
        );

    $this->actingAs($readOnlyStaff, 'admin')
        ->post(route('admin.master.work-shifts.store'), workShiftPayload())
        ->assertForbidden();

    $this->actingAs($readOnlyStaff, 'admin')
        ->patch(route('admin.master.work-shifts.update', $workShift), workShiftPayload())
        ->assertForbidden();

    $this->actingAs($readOnlyStaff, 'admin')
        ->delete(route('admin.master.work-shifts.destroy', $workShift))
        ->assertForbidden();
});
