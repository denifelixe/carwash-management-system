<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\Lead;
use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-30 10:00', 'Asia/Jakarta'));
});

/** @param array<string, bool> $abilities */
function memberStaff(array $abilities): Admin
{
    $role = AdminRole::query()->create([
        'key' => 'member_'.uniqid(),
        'name' => 'Member Staff',
        'description' => 'Role uji akses member.',
        'is_active' => true,
    ]);

    $role->modules()->attach(
        AdminModule::query()->where('key', 'members')->firstOrFail(),
        [
            'can_create' => $abilities['create'] ?? false,
            'can_read' => $abilities['read'] ?? false,
            'can_update' => $abilities['update'] ?? false,
            'can_delete' => false,
        ],
    );

    return Admin::factory()->create(['role_id' => $role->id]);
}

/** @return array<string, mixed> */
function memberPayload(array $overrides = []): array
{
    return array_replace([
        'name' => '  Budi   Santoso  ',
        'phone' => ' 081234567890 ',
        'email' => '',
        'vehicles' => [
            ['name' => 'Toyota Avanza', 'plate' => 'b 1234 cde', 'type' => 'Mobil'],
            ['name' => 'Honda Vario', 'plate' => 'B 5566 TY', 'type' => 'Motor'],
        ],
    ], $overrides);
}

test('guests cannot open the member module', function () {
    $this->get(route('admin.members.index'))
        ->assertRedirect(route('admin.login'));
});

test('staff without read access cannot open the member module', function () {
    $this->actingAs(memberStaff(['read' => false]), 'admin')
        ->get(route('admin.members.index'))
        ->assertForbidden();
});

test('an owner sees the live paginated member module and sidebar wiring', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.members.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Customers')
                ->where('mode', 'live')
                ->where('capabilities.create', true)
                ->where('capabilities.update', true)
                ->where('members.meta.perPage', 15)
                ->where('filters.status', 'Semua')
                ->where('filters.account', 'Semua')
                ->where('accountFilters', ['Punya akun portal', 'Tidak punya akun portal'])
                ->where('stampTarget', 10)
                ->where('modules.5.key', 'members')
                ->where('modules.5.label', 'Member')
                ->where('modules.5.active', true)
                ->where('modules.5.enabled', true)
                ->where('modules.5.href', route('admin.members.index', absolute: false)),
        );
});

test('member stamps and detail are derived from non-cancelled orders', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create(['password' => null, 'email' => null]);
    MemberVehicle::factory()->for($member)->create(['plate' => 'B1234CDE']);
    Order::factory()->for($member)->create([
        'status' => 'selesai',
        'stamps_earned' => 12,
        'service_date' => '2026-08-29',
    ]);
    Order::factory()->for($member)->create([
        'status' => 'batal',
        'stamps_earned' => 9,
        'service_date' => '2026-08-30',
    ]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.members.index', ['member' => $member->id]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('members.data.0.stamps', 2)
                ->where('members.data.0.lifetimeStamps', 12)
                ->where('members.data.0.hasAccount', false)
                ->where('members.data.0.email', '')
                ->where('memberDetail.customer.id', $member->id)
                ->has('memberDetail.orders', 2)
                ->has('memberDetail.stampHistory', 1)
                ->where('memberDetail.stampHistory.0.type', 'earn')
                ->where('stats.circulatingStamps', 2),
        );

    $this->actingAs($owner, 'admin')
        ->get(route('admin.members.index', ['member' => 999999]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('memberDetail', null));
});

test('members can be searched by name phone and normalized plate and filtered by status', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create([
        'name' => 'Budi Santoso',
        'phone' => '081200001111',
        'is_active' => false,
    ]);
    MemberVehicle::factory()->for($member)->create(['plate' => 'B1234CDE']);

    foreach (['Budi', '081200001111', 'b 1234 cde'] as $query) {
        $this->actingAs($owner, 'admin')
            ->get(route('admin.members.index', ['q' => $query]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('members.data.0.id', $member->id));
    }

    $this->actingAs($owner, 'admin')
        ->get(route('admin.members.index', ['status' => 'tidak aktif']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('members.data.0.id', $member->id));
});

test('member status and portal account filters can be combined independently', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    Member::factory()->create([
        'name' => 'Aktif Dengan Akun',
        'is_active' => true,
    ]);
    $activeWithoutAccount = Member::factory()->create([
        'name' => 'Aktif Tanpa Akun',
        'is_active' => true,
        'password' => null,
    ]);
    Member::factory()->create([
        'name' => 'Tidak Aktif Tanpa Akun',
        'is_active' => false,
        'password' => null,
    ]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.members.index', [
            'status' => 'aktif',
            'account' => 'Tidak punya akun portal',
        ]))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('filters.status', 'aktif')
                ->where('filters.account', 'Tidak punya akun portal')
                ->where('members.meta.total', 1)
                ->where('members.data.0.id', $activeWithoutAccount->id),
        );
});

test('the member list paginates fifteen rows at a time', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    Member::factory()->count(20)->create();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.members.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('members.data', 15)
                ->where('members.meta.lastPage', 2)
                ->where('members.meta.total', 20),
        );
});

test('an owner can create edit and toggle a member without portal credentials', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.members.store'), memberPayload())
        ->assertRedirect();

    $member = Member::query()->sole();
    $vehicles = $member->vehicles()->orderBy('id')->get();

    expect($member->name)->toBe('Budi Santoso')
        ->and($member->email)->toBeNull()
        ->and($member->password)->toBeNull()
        ->and($vehicles)->toHaveCount(2)
        ->and($vehicles[0]->plate)->toBe('B1234CDE')
        ->and($vehicles[0]->is_primary)->toBeTrue()
        ->and($vehicles[1]->is_primary)->toBeFalse();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.members.update', $member), memberPayload([
            'name' => 'Budi Baru',
            'vehicles' => [
                ['id' => $vehicles[0]->id, 'name' => 'Avanza Baru', 'plate' => 'B 1234 CDE', 'type' => 'Mobil'],
                ['name' => 'Yamaha NMax', 'plate' => 'B 9000 NM', 'type' => 'Motor'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect($member->refresh()->name)->toBe('Budi Baru')
        ->and($member->vehicles()->whereKey($vehicles[1]->id)->exists())->toBeFalse()
        ->and($member->vehicles)->toHaveCount(2);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.members.status.update', $member), ['is_active' => false])
        ->assertSessionHasNoErrors();
    expect($member->refresh()->is_active)->toBeFalse();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.members.status.update', $member), ['is_active' => true])
        ->assertSessionHasNoErrors();
    expect($member->refresh()->is_active)->toBeTrue();
});

test('member validation rejects duplicate identity and invalid vehicle data', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $existing = Member::factory()->create([
        'phone' => '081234567890',
        'email' => 'used@example.com',
    ]);
    MemberVehicle::factory()->for($existing)->create(['plate' => 'B1234CDE']);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.members.store'), memberPayload(['email' => 'used@example.com']))
        ->assertSessionHasErrors(['phone', 'email', 'vehicles.0.plate']);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.members.store'), memberPayload([
            'phone' => '081299998888',
            'vehicles' => [
                ['name' => 'Satu', 'plate' => 'D 1000 AA', 'type' => 'Bus'],
                ['name' => 'Dua', 'plate' => 'd1000aa', 'type' => 'Mobil'],
            ],
        ]))
        ->assertSessionHasErrors(['vehicles.0.type', 'vehicles.1.plate']);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.members.store'), memberPayload([
            'phone' => '081299997777',
            'vehicles' => [],
        ]))
        ->assertSessionHasErrors('vehicles');
});

test('updates accept a members own identity but reject another members vehicle id', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create();
    $vehicle = MemberVehicle::factory()->for($member)->create(['plate' => 'B1111AA']);
    $otherVehicle = MemberVehicle::factory()->create(['plate' => 'B2222BB']);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.members.update', $member), [
            'name' => $member->name,
            'phone' => $member->phone,
            'email' => $member->email,
            'vehicles' => [['id' => $vehicle->id, 'name' => $vehicle->name, 'plate' => 'b 1111 aa', 'type' => $vehicle->type]],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.members.update', $member), [
            'name' => $member->name,
            'phone' => $member->phone,
            'email' => $member->email,
            'vehicles' => [['id' => $otherVehicle->id, 'name' => 'Bukan Milik', 'plate' => 'B3333CC', 'type' => 'Mobil']],
        ])
        ->assertSessionHasErrors('vehicles.0.id');
});

test('read only staff see no write capabilities and cannot mutate members', function () {
    $staff = memberStaff(['read' => true]);
    $member = Member::factory()->create();
    MemberVehicle::factory()->for($member)->create();

    $this->actingAs($staff, 'admin')
        ->get(route('admin.members.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('capabilities.create', false)
                ->where('capabilities.update', false),
        );

    $this->actingAs($staff, 'admin')
        ->post(route('admin.members.store'), memberPayload())
        ->assertForbidden();
    $this->actingAs($staff, 'admin')
        ->patch(route('admin.members.update', $member), memberPayload())
        ->assertForbidden();
    $this->actingAs($staff, 'admin')
        ->patch(route('admin.members.status.update', $member), ['is_active' => false])
        ->assertForbidden();
});

test('registering a member from the module closes the lead holding that plate', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $lead = Lead::factory()->create(['vehicle_plate' => 'B1234CDE']);
    $untouched = Lead::factory()->create(['vehicle_plate' => 'D5555ZZ']);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.members.store'), memberPayload([
            'vehicles' => [
                ['name' => 'Toyota Avanza', 'plate' => 'b 1234 cde', 'type' => 'Mobil'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    $member = Member::query()->sole();

    expect($lead->refresh()->converted_member_id)->toBe($member->id)
        ->and($lead->converted_at)->not->toBeNull()
        ->and($untouched->refresh()->converted_member_id)->toBeNull();
});
