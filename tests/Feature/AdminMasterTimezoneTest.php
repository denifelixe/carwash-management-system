<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\AppSetting;
use App\Support\AppSettings;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

/**
 * @param  array<string, bool>  $abilities
 */
function timezoneStaff(array $abilities): Admin
{
    $role = AdminRole::query()->create([
        'key' => 'timezone_'.uniqid(),
        'name' => 'Timezone Staff',
        'description' => 'Role uji akses master timezone.',
        'is_active' => true,
    ]);

    $role->modules()->attach(
        AdminModule::query()->where('key', 'master_timezone')->firstOrFail(),
        [
            'can_create' => $abilities['create'] ?? false,
            'can_read' => $abilities['read'] ?? false,
            'can_update' => $abilities['update'] ?? false,
            'can_delete' => $abilities['delete'] ?? false,
        ],
    );

    return Admin::factory()->create(['role_id' => $role->id]);
}

test('guests cannot open the master timezone module', function () {
    $this->get(route('admin.master.timezone.index'))
        ->assertRedirect(route('admin.login'));
});

test('an owner sees the three indonesian zones with the configured one selected', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.master.timezone.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/master/Timezone')
                ->where('mode', 'live')
                ->where('timezone', 'Asia/Jakarta')
                ->has('timezones', 3)
                ->where('timezones.0.id', 'Asia/Jakarta')
                ->where('timezones.0.code', 'WIB')
                ->where('timezones.1.code', 'WITA')
                ->where('timezones.2.code', 'WIT')
                ->where('capabilities.update', true)
                ->where('modules.11.key', 'master')
                ->where('modules.11.active', true)
                ->where('modules.11.children.3.key', 'master_timezone')
                ->where('modules.11.children.3.active', true)
                ->where(
                    'modules.11.children.3.href',
                    route('admin.master.timezone.index', absolute: false),
                ),
        );
});

test('an owner can move the outlet onto another zone', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.master.timezone.update'), ['timezone' => 'Asia/Makassar'])
        ->assertRedirect(route('admin.master.timezone.index'))
        ->assertSessionHas('success');

    $setting = AppSetting::query()->where('key', 'timezone')->firstOrFail();

    /* Not expect($setting)->value: Pest's own value property shadows it. */
    expect($setting->value)->toBe('Asia/Makassar');
    expect($setting->updated_by_admin_id)->toBe($owner->id);

    expect(AppSettings::timezone())->toBe('Asia/Makassar');
});

/**
 * The whole point of the setting: what a datetime column receives is the
 * outlet's own wall clock, so moving the zone moves the clock the app writes.
 */
test('the configured zone is the clock every stored datetime is written in', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $this->travelTo(CarbonImmutable::parse('2026-08-30 23:30', 'Asia/Jakarta'));

    expect(now()->format('Y-m-d H:i'))->toBe('2026-08-30 23:30');

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.master.timezone.update'), ['timezone' => 'Asia/Jayapura']);

    /* Same instant, two hours later on the Jayapura clock — and a day over. */
    expect(now()->format('Y-m-d H:i'))->toBe('2026-08-31 01:30');
});

test('a zone outside the indonesian catalogue is refused', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->from(route('admin.master.timezone.index'))
        ->patch(route('admin.master.timezone.update'), ['timezone' => 'Europe/Paris'])
        ->assertSessionHasErrors('timezone');

    expect(AppSettings::timezone())->toBe('Asia/Jakarta');
});

test('staff access follows master timezone capabilities', function () {
    $this->actingAs(timezoneStaff(['read' => false]), 'admin')
        ->get(route('admin.master.timezone.index'))
        ->assertForbidden();

    $readOnlyStaff = timezoneStaff(['read' => true]);

    $this->actingAs($readOnlyStaff, 'admin')
        ->get(route('admin.master.timezone.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page->where('capabilities.update', false),
        );

    $this->actingAs($readOnlyStaff, 'admin')
        ->patch(route('admin.master.timezone.update'), ['timezone' => 'Asia/Makassar'])
        ->assertForbidden();

    expect(AppSettings::timezone())->toBe('Asia/Jakarta');
});
