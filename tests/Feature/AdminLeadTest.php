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
    $this->travelTo(CarbonImmutable::parse('2026-09-03 10:00', 'Asia/Jakarta'));
});

/** @param array<string, bool> $abilities */
function leadStaff(array $abilities): Admin
{
    $role = AdminRole::query()->create([
        'key' => 'lead_'.uniqid(),
        'name' => 'Lead Staff',
        'description' => 'Role uji akses lead.',
        'is_active' => true,
    ]);

    $role->modules()->attach(
        AdminModule::query()->where('key', 'leads')->firstOrFail(),
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
function leadPayload(array $overrides = []): array
{
    return array_replace([
        'name' => '  Budi   Santoso  ',
        'phone' => ' 081234567890 ',
        'vehicle_name' => '  Toyota   Calya  ',
        'vehicle_plate' => 'b 9876 abc',
        'notes' => '',
    ], $overrides);
}

test('guests cannot open the lead module', function () {
    $this->get(route('admin.leads.index'))
        ->assertRedirect(route('admin.login'));
});

test('staff without read access cannot open the lead module', function () {
    $this->actingAs(leadStaff(['read' => false]), 'admin')
        ->get(route('admin.leads.index'))
        ->assertForbidden();
});

test('an owner sees the live paginated lead module and sidebar wiring', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.leads.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Leads')
                ->where('mode', 'live')
                ->where('capabilities.create', true)
                ->where('capabilities.update', true)
                ->where('leads.meta.perPage', 15)
                ->where('filters.status', 'Semua')
                ->where('filters.conversion', 'Belum jadi member')
                ->where('conversionFilters', ['Semua', 'Belum jadi member', 'Sudah jadi member'])
                ->where('modules.6.key', 'leads')
                ->where('modules.6.label', 'Leads')
                ->where('modules.6.active', true)
                ->where('modules.6.enabled', true)
                ->where('modules.6.href', route('admin.leads.index', absolute: false)),
        );
});

test('the working list hides converted leads until the filter asks for them', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create();
    $open = Lead::factory()->create(['name' => 'Masih Prospek']);
    $converted = Lead::factory()->create([
        'name' => 'Sudah Gabung',
        'converted_member_id' => $member->id,
        'converted_at' => now(),
    ]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.leads.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('leads.meta.total', 1)
                ->where('leads.data.0.id', $open->id)
                ->where('leads.data.0.isConverted', false)
                ->where('stats.total', 2)
                ->where('stats.open', 1)
                ->where('stats.converted', 1),
        );

    $this->actingAs($owner, 'admin')
        ->get(route('admin.leads.index', ['conversion' => 'Sudah jadi member']))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('leads.meta.total', 1)
                ->where('leads.data.0.id', $converted->id)
                ->where('leads.data.0.isConverted', true)
                ->where('leads.data.0.convertedMemberId', $member->id),
        );
});

test('leads can be searched by name phone and normalized plate', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $lead = Lead::factory()->create([
        'name' => 'Budi Santoso',
        'phone' => '081200001111',
        'vehicle_plate' => 'B1234CDE',
    ]);
    Lead::factory()->create(['name' => 'Orang Lain', 'phone' => '081999998888']);

    foreach (['Budi', '081200001111', 'b 1234 cde'] as $query) {
        $this->actingAs($owner, 'admin')
            ->get(route('admin.leads.index', ['q' => $query]))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('leads.meta.total', 1)
                    ->where('leads.data.0.id', $lead->id),
            );
    }
});

test('lead visits and spend are derived from non cancelled orders', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $lead = Lead::factory()->create();
    Order::factory()->for($lead)->create([
        'status' => 'selesai',
        'total' => 50000,
        'service_date' => '2026-09-02',
    ]);
    Order::factory()->for($lead)->create([
        'status' => 'batal',
        'total' => 90000,
        'service_date' => '2026-09-03',
    ]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.leads.index', ['lead' => $lead->id]))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('leads.data.0.visits', 1)
                ->where('leads.data.0.spend', 50000)
                ->where('leadDetail.lead.id', $lead->id)
                ->has('leadDetail.orders', 2),
        );

    $this->actingAs($owner, 'admin')
        ->get(route('admin.leads.index', ['lead' => 999999]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('leadDetail', null));
});

test('the lead list paginates fifteen rows at a time', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    Lead::factory()->count(20)->create();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.leads.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('leads.data', 15)
                ->where('leads.meta.lastPage', 2)
                ->where('leads.meta.total', 20),
        );
});

test('an owner can create edit and toggle a lead', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.leads.store'), leadPayload())
        ->assertRedirect();

    $lead = Lead::query()->sole();

    expect($lead->name)->toBe('Budi Santoso')
        ->and($lead->phone)->toBe('081234567890')
        ->and($lead->vehicle_name)->toBe('Toyota Calya')
        ->and($lead->vehicle_plate)->toBe('B9876ABC')
        ->and($lead->notes)->toBeNull()
        ->and($lead->is_active)->toBeTrue()
        ->and($lead->converted_member_id)->toBeNull();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.leads.update', $lead), leadPayload([
            'name' => 'Budi Baru',
            'vehicle_plate' => 'B 9876 ABC',
            'notes' => 'Minta ditelepon bulan depan',
        ]))
        ->assertSessionHasNoErrors();

    expect($lead->refresh()->name)->toBe('Budi Baru')
        ->and($lead->notes)->toBe('Minta ditelepon bulan depan');

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.leads.status.update', $lead), ['is_active' => false])
        ->assertSessionHasNoErrors();
    expect($lead->refresh()->is_active)->toBeFalse();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.leads.index', ['status' => 'tidak aktif']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('leads.data.0.id', $lead->id));
});

test('lead validation rejects a blank name and a plate already taken', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    Lead::factory()->create(['vehicle_plate' => 'B9876ABC']);
    MemberVehicle::factory()->create(['plate' => 'B1111AA']);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.leads.store'), leadPayload(['name' => '   ']))
        ->assertSessionHasErrors('name');

    $this->actingAs($owner, 'admin')
        ->post(route('admin.leads.store'), leadPayload())
        ->assertSessionHasErrors('vehicle_plate');

    $this->actingAs($owner, 'admin')
        ->post(route('admin.leads.store'), leadPayload(['vehicle_plate' => 'b 1111 aa']))
        ->assertSessionHasErrors('vehicle_plate');
});

test('an update accepts the leads own plate but not another leads plate', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $lead = Lead::factory()->create(['vehicle_plate' => 'B2222BB']);
    Lead::factory()->create(['vehicle_plate' => 'B3333CC']);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.leads.update', $lead), leadPayload(['vehicle_plate' => 'b 2222 bb']))
        ->assertSessionHasNoErrors();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.leads.update', $lead), leadPayload(['vehicle_plate' => 'B3333CC']))
        ->assertSessionHasErrors('vehicle_plate');
});

test('read only staff see no write capabilities and cannot mutate leads', function () {
    $staff = leadStaff(['read' => true]);
    $lead = Lead::factory()->create();

    $this->actingAs($staff, 'admin')
        ->get(route('admin.leads.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('capabilities.create', false)
                ->where('capabilities.update', false),
        );

    $this->actingAs($staff, 'admin')
        ->post(route('admin.leads.store'), leadPayload())
        ->assertForbidden();
    $this->actingAs($staff, 'admin')
        ->patch(route('admin.leads.update', $lead), leadPayload())
        ->assertForbidden();
    $this->actingAs($staff, 'admin')
        ->patch(route('admin.leads.status.update', $lead), ['is_active' => false])
        ->assertForbidden();
});
