<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceVariation;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

/** @param array<string, bool> $abilities */
function serviceStaff(array $abilities): Admin
{
    $role = AdminRole::query()->create([
        'key' => 'master_'.uniqid(),
        'name' => 'Master Staff',
        'description' => 'Role uji akses master layanan.',
        'is_active' => true,
    ]);
    $role->modules()->attach(
        AdminModule::query()->where('key', 'master_services')->firstOrFail(),
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
function servicePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Cuci Kilat',
        'category' => 'Cuci Mobil',
        'variations' => null,
        'service_variations' => [[
            'id' => null,
            'variations' => null,
            'price' => 55000,
            'is_active' => true,
        ]],
        'stamps' => 1,
        'icon' => '🚿',
        'description' => 'Cuci cepat 1 kali proses.',
        'is_popular' => false,
        'is_active' => true,
    ], $overrides);
}

function attachVariation(Order $order, ServiceVariation $variation, int $quantity = 1): void
{
    $service = $variation->service;
    $order->serviceVariations()->attach($variation, [
        'service_name' => $service->name,
        'variations' => $variation->variations === null ? null : json_encode($variation->variations),
        'unit_price' => $variation->price,
        'quantity' => $quantity,
        'total_price' => $variation->price * $quantity,
        'stamps' => $service->stamps,
    ]);
}

test('guests cannot open the master service module', function () {
    $this->get(route('admin.master.services.index'))->assertRedirect(route('admin.login'));
});

test('the final schema stores prices only on service variations', function () {
    expect(Schema::hasColumn('services', 'variations'))->toBeTrue()
        ->and(Schema::hasColumn('services', 'price'))->toBeFalse()
        ->and(Schema::hasTable('service_variations'))->toBeTrue()
        ->and(Schema::hasColumn('order_services', 'service_variation_id'))->toBeTrue()
        ->and(Schema::hasColumn('order_services', 'service_id'))->toBeFalse()
        ->and(Schema::hasTable('service_groups'))->toBeFalse();
});

test('an owner sees logical services and their variation matrix', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create([
        'name' => 'Coating Lite',
        'variations' => ['Ukuran' => ['Small', 'Large']],
    ]);
    $default = $service->serviceVariations()->firstOrFail();
    $default->update(['variations' => ['Ukuran' => 'Small'], 'price' => 800000]);
    ServiceVariation::factory()->for($service)->create([
        'variations' => ['Ukuran' => 'Large'],
        'price' => 1100000,
    ]);

    $this->actingAs($owner, 'admin')->get(route('admin.master.services.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/master/Services')
            ->where('mode', 'live')
            ->has('services', 1)
            ->where('services.0.name', 'Coating Lite')
            ->where('services.0.variations.Ukuran.0', 'Small')
            ->has('services.0.service_variations', 2)
            ->where('capabilities.create', true)
            ->where('modules.11.children.0.key', 'master_services'));
});

test('an owner can create a service without variation axes and it still gets one price row', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')->post(route('admin.master.services.store'), servicePayload())
        ->assertRedirect(route('admin.master.services.index'))->assertSessionHasNoErrors();

    $service = Service::query()->where('name', 'Cuci Kilat')->firstOrFail();
    $variation = $service->serviceVariations()->sole();

    expect($service->variations)->toBeNull()
        ->and($variation->variations)->toBeNull()
        ->and($variation->price)->toBe(55000);
});

test('an owner can create a complete multi attribute variation matrix', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $configuration = ['Ukuran' => ['Small', 'Large'], 'Paket' => ['A', 'B']];
    $rows = collect(['Small', 'Large'])->crossJoin(['A', 'B'])->values()->map(
        fn (array $values, int $index): array => [
            'id' => null,
            'variations' => ['Ukuran' => $values[0], 'Paket' => $values[1]],
            'price' => 100000 + ($index * 10000),
            'is_active' => true,
        ],
    )->all();

    $this->actingAs($owner, 'admin')->post(route('admin.master.services.store'), servicePayload([
        'name' => 'Detailing Matrix',
        'variations' => $configuration,
        'service_variations' => $rows,
    ]))->assertSessionHasNoErrors();

    $service = Service::query()->where('name', 'Detailing Matrix')->firstOrFail();
    expect($service->variations)->toBe($configuration)
        ->and($service->serviceVariations()->count())->toBe(4);
});

test('incomplete duplicate and foreign variation combinations are rejected', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $otherVariation = Service::factory()->create()->serviceVariations()->firstOrFail();

    $this->actingAs($owner, 'admin')->post(route('admin.master.services.store'), servicePayload([
        'variations' => ['Ukuran' => ['Small', 'Large']],
        'service_variations' => [
            ['id' => $otherVariation->id, 'variations' => ['Ukuran' => 'Small'], 'price' => 1, 'is_active' => true],
            ['id' => null, 'variations' => ['Ukuran' => 'Small'], 'price' => 2, 'is_active' => true],
        ],
    ]))->assertSessionHasErrors('service_variations');
});

test('an active service needs an active variation', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $this->actingAs($owner, 'admin')->post(route('admin.master.services.store'), servicePayload([
        'service_variations' => [[
            'id' => null, 'variations' => null, 'price' => 55000, 'is_active' => false,
        ]],
    ]))->assertSessionHasErrors('service_variations');
});

test('updating a service preserves matching variation ids and retires used removed rows', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $service = Service::factory()->create(['variations' => ['Ukuran' => ['Small', 'Large']]]);
    $small = $service->serviceVariations()->firstOrFail();
    $small->update(['variations' => ['Ukuran' => 'Small'], 'price' => 50000]);
    $large = ServiceVariation::factory()->for($service)->create([
        'variations' => ['Ukuran' => 'Large'], 'price' => 75000,
    ]);
    attachVariation(Order::factory()->create(), $large);

    $this->actingAs($owner, 'admin')->patch(route('admin.master.services.update', $service), servicePayload([
        'name' => $service->name,
        'variations' => ['Ukuran' => ['Small']],
        'service_variations' => [[
            'id' => $small->id, 'variations' => ['Ukuran' => 'Small'], 'price' => 60000, 'is_active' => true,
        ]],
    ]))->assertSessionHasNoErrors();

    expect($small->refresh()->price)->toBe(60000)
        ->and($large->refresh()->is_active)->toBeFalse();
});

test('service names stay unique and icons use the shared picker list', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    Service::factory()->create(['name' => 'Cuci Kilat']);
    $this->actingAs($owner, 'admin')->post(route('admin.master.services.store'), servicePayload())
        ->assertSessionHasErrors('name');
    $this->actingAs($owner, 'admin')->post(route('admin.master.services.store'), servicePayload([
        'name' => 'Nama Lain', 'icon' => '🍕',
    ]))->assertSessionHasErrors('icon');
});

test('a service can only be deleted before any variation is ordered', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $unused = Service::factory()->create();
    $used = Service::factory()->create();
    attachVariation(Order::factory()->create(), $used->serviceVariations()->firstOrFail());

    $this->actingAs($owner, 'admin')->delete(route('admin.master.services.destroy', $unused))
        ->assertSessionHasNoErrors();
    $this->actingAs($owner, 'admin')->from(route('admin.master.services.index'))
        ->delete(route('admin.master.services.destroy', $used))->assertSessionHasErrors('service');

    expect($unused->fresh())->toBeNull()->and($used->fresh())->not->toBeNull();
});

test('master service permissions protect read and write operations', function () {
    $blocked = serviceStaff(['read' => false]);
    $reader = serviceStaff(['read' => true]);
    $service = Service::factory()->create();

    $this->actingAs($blocked, 'admin')->get(route('admin.master.services.index'))->assertForbidden();
    $this->actingAs($reader, 'admin')->get(route('admin.master.services.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('capabilities.create', false)->where('capabilities.update', false)->where('capabilities.delete', false));
    $this->actingAs($reader, 'admin')->post(route('admin.master.services.store'), servicePayload())->assertForbidden();
    $this->actingAs($reader, 'admin')->patch(route('admin.master.services.update', $service), servicePayload())->assertForbidden();
    $this->actingAs($reader, 'admin')->delete(route('admin.master.services.destroy', $service))->assertForbidden();
});

test('services retain a flat configurable catalog order', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $first = Service::factory()->create(['name' => 'Cuci A', 'sort_order' => 1]);
    $second = Service::factory()->create(['name' => 'Cuci B', 'sort_order' => 2]);
    $third = Service::factory()->create(['name' => 'Cuci C', 'sort_order' => 3]);

    $this->actingAs($owner, 'admin')->patch(route('admin.master.services.order.update'), [
        'ids' => [$third->id, $first->id, $second->id],
    ])->assertSessionHasNoErrors();

    expect($third->refresh()->sort_order)->toBe(1)
        ->and($first->refresh()->sort_order)->toBe(2)
        ->and($second->refresh()->sort_order)->toBe(3);
});

test('partial service ordering is rejected and new services append to the end', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $first = Service::factory()->create(['sort_order' => 1]);
    Service::factory()->create(['sort_order' => 7]);
    $this->actingAs($owner, 'admin')->patch(route('admin.master.services.order.update'), ['ids' => [$first->id]])
        ->assertSessionHasErrors('ids');
    $this->actingAs($owner, 'admin')->post(route('admin.master.services.store'), servicePayload())
        ->assertSessionHasNoErrors();
    expect(Service::query()->where('name', 'Cuci Kilat')->firstOrFail()->sort_order)->toBe(8);
});

test('the master page contains variation accordion and matrix controls', function () {
    $source = file_get_contents(resource_path('js/pages/admin/master/Services.vue'));
    expect($source)->toContain('service_variations')
        ->toContain('regenerateVariations')
        ->toContain('cloneVariationConfiguration')
        ->not->toContain('structuredClone')
        ->toContain('Jenis variation')
        ->toContain('toggleExpanded(service.id)')
        ->toContain('Variasi dan harga')
        ->not->toContain('Kombinasi')
        ->toContain('Ubah urutan');
});

test('the master page reorders services by dragging a row behind a floating save bar', function () {
    $source = file_get_contents(resource_path('js/pages/admin/master/Services.vue'));

    expect($source)
        ->toContain('data-service-row')
        ->toContain('@pointerdown="startDrag(service, $event)"')
        ->toContain('<GripVertical class="h-4 w-4" />')
        ->toContain('window.scrollBy(')
        ->toContain('requestAnimationFrame(stepDrag)')
        ->toContain('function cancelSorting(): void')
        ->toContain('Simpan urutan')
        ->toContain(':disabled="orderForm.processing || !isOrderDirty"')
        ->toContain('<Teleport to="body">');
});

test('the master page filters services with multi select category chips', function () {
    $source = file_get_contents(resource_path('js/pages/admin/master/Services.vue'));

    expect($source)
        ->toContain('const selectedCategories = ref<string[]>([])')
        ->toContain('function toggleCategory(option: string): void')
        ->toContain('selectedCategories.value.includes(service.category)')
        ->toContain('@click="selectedCategories = []"')
        ->not->toContain('Layanan populer"')
        ->not->toContain('Harga rata-rata');
});
