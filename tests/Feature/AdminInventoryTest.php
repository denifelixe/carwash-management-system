<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\StockItem;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Asia/Jakarta'));
});

/** @param array<string, bool> $abilities */
function inventoryStaff(array $abilities): Admin
{
    $role = AdminRole::query()->create([
        'key' => 'stock_'.uniqid(),
        'name' => 'Stock Staff',
        'description' => 'Role uji akses stok.',
        'is_active' => true,
    ]);

    $role->modules()->attach(
        AdminModule::query()->where('key', 'inventory')->firstOrFail(),
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
function stockItemPayload(array $overrides = []): array
{
    return array_replace([
        'sku' => '  snw-001  ',
        'name' => '  Snow   Foam pH Netral  ',
        'category' => '  Bahan   Cuci  ',
        'unit' => ' galon ',
        'quantity' => 10,
        'min_quantity' => 4,
        'unit_cost' => 320000,
        'supplier' => '  PT Kilau   Kimia  ',
        'notes' => '',
    ], $overrides);
}

test('guests cannot open the inventory module', function () {
    $this->get(route('admin.inventory.index'))
        ->assertRedirect(route('admin.login'));
});

test('staff without read access cannot open the inventory module', function () {
    $this->actingAs(inventoryStaff(['read' => false]), 'admin')
        ->get(route('admin.inventory.index'))
        ->assertForbidden();
});

test('an owner sees the live paginated inventory module and sidebar wiring', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.inventory.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Inventory')
                ->where('mode', 'live')
                ->where('capabilities.create', true)
                ->where('capabilities.update', true)
                ->where('items.meta.perPage', 15)
                ->where('movements.meta.perPage', 10)
                ->where('filters.status', 'aktif')
                ->where('filters.stock', 'Semua')
                ->where('movementTypes', ['masuk', 'keluar', 'penyesuaian'])
                ->where('stockFilters', ['Semua', 'Stok menipis'])
                ->where('modules.7.key', 'inventory')
                ->where('modules.7.label', 'Stock Inventory')
                ->where('modules.7.active', true)
                ->where('modules.7.enabled', true)
                ->where('modules.7.href', route('admin.inventory.index', absolute: false)),
        );
});

test('a new item normalises its sku and files its opening stock as a movement', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.inventory.store'), stockItemPayload())
        ->assertRedirect(route('admin.inventory.index'))
        ->assertSessionHas('success');

    $item = StockItem::query()->sole();

    expect($item->sku)->toBe('SNW-001')
        ->and($item->name)->toBe('Snow Foam pH Netral')
        ->and($item->category)->toBe('Bahan Cuci')
        ->and($item->supplier)->toBe('PT Kilau Kimia')
        ->and($item->notes)->toBeNull()
        ->and($item->quantity)->toBe(10);

    $movement = $item->movements()->sole();

    expect($movement->type)->toBe('masuk')
        ->and($movement->quantity)->toBe(10)
        ->and($movement->quantity_before)->toBe(0)
        ->and($movement->quantity_after)->toBe(10)
        ->and($movement->note)->toBe('Stok awal')
        ->and($movement->recorded_by_admin_id)->toBe($owner->id);
});

test('an item created with no opening stock records no movement', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.inventory.store'), stockItemPayload(['quantity' => 0]));

    expect(StockItem::query()->sole()->quantity)->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0);
});

test('a duplicate sku is refused however it was typed', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    StockItem::factory()->create(['sku' => 'SNW-001']);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.inventory.store'), stockItemPayload(['sku' => ' snw-001 ']))
        ->assertSessionHasErrors('sku');

    expect(StockItem::query()->count())->toBe(1);
});

test('each movement type moves the stock and records both sides of the change', function (
    string $type,
    int $quantity,
    int $expectedDelta,
    int $expectedAfter,
) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $item = StockItem::factory()->create(['quantity' => 20, 'min_quantity' => 5]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.inventory.movements.store', $item), [
            'type' => $type,
            'quantity' => $quantity,
            'note' => 'Uji pergerakan',
        ])
        ->assertSessionHas('success');

    $movement = $item->movements()->sole();

    expect($movement->type)->toBe($type)
        ->and($movement->quantity)->toBe($expectedDelta)
        ->and($movement->quantity_before)->toBe(20)
        ->and($movement->quantity_after)->toBe($expectedAfter)
        ->and($item->fresh()->quantity)->toBe($expectedAfter);
})->with([
    'masuk' => ['masuk', 6, 6, 26],
    'keluar' => ['keluar', 6, -6, 14],
    'penyesuaian negatif' => ['penyesuaian', -4, -4, 16],
    'penyesuaian positif' => ['penyesuaian', 4, 4, 24],
]);

test('an out larger than the stock on hand is refused and changes nothing', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $item = StockItem::factory()->create(['quantity' => 3]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.inventory.movements.store', $item), [
            'type' => 'keluar',
            'quantity' => 4,
        ])
        ->assertSessionHasErrors('quantity');

    expect($item->fresh()->quantity)->toBe(3)
        ->and(StockMovement::query()->count())->toBe(0);
});

test('a correction that would push the stock below zero is refused', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $item = StockItem::factory()->create(['quantity' => 2]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.inventory.movements.store', $item), [
            'type' => 'penyesuaian',
            'quantity' => -5,
        ])
        ->assertSessionHasErrors('quantity');

    expect($item->fresh()->quantity)->toBe(2);
});

test('a correction of zero is refused', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $item = StockItem::factory()->create(['quantity' => 10]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.inventory.movements.store', $item), [
            'type' => 'penyesuaian',
            'quantity' => 0,
        ])
        ->assertSessionHasErrors('quantity');
});

test('a restock at a new price re-bases what the stock is worth', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $item = StockItem::factory()->create(['quantity' => 4, 'unit_cost' => 50_000]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.inventory.movements.store', $item), [
            'type' => 'masuk',
            'quantity' => 6,
            'unit_cost' => 65_000,
        ]);

    expect($item->fresh()->unit_cost)->toBe(65_000)
        ->and($item->movements()->sole()->unit_cost)->toBe(65_000);
});

test('editing an item never touches the stock on hand', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $item = StockItem::factory()->create(['quantity' => 17, 'name' => 'Nama Lama']);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.inventory.update', $item), [
            'sku' => $item->sku,
            'name' => 'Nama Baru',
            'category' => $item->category,
            'unit' => $item->unit,
            'quantity' => 999,
            'min_quantity' => 3,
            'unit_cost' => 12_000,
            'supplier' => null,
            'notes' => null,
        ])
        ->assertSessionHas('success');

    expect($item->fresh()->name)->toBe('Nama Baru')
        ->and($item->fresh()->quantity)->toBe(17);
});

test('a deactivated item leaves the picker but keeps its history', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $item = StockItem::factory()->create(['quantity' => 9]);
    StockMovement::factory()->for($item, 'stockItem')->create();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.inventory.status.update', $item), ['is_active' => false])
        ->assertSessionHas('success');

    expect($item->fresh()->is_active)->toBeFalse()
        ->and($item->movements()->count())->toBe(1);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.inventory.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('itemOptions', [])
                ->where('movements.meta.total', 1),
        );

    $this->actingAs($owner, 'admin')
        ->post(route('admin.inventory.movements.store', $item), ['type' => 'masuk', 'quantity' => 1])
        ->assertSessionHasErrors('type');
});

test('the low stock filter and the stats agree on which items are short', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $short = StockItem::factory()->lowStock()->create(['name' => 'Hampir Habis']);
    StockItem::factory()->create(['name' => 'Masih Banyak', 'quantity' => 50, 'min_quantity' => 5]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.inventory.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('items.meta.total', 2)
                ->where('stats.totalItems', 2)
                ->where('stats.lowStock', 1),
        );

    $this->actingAs($owner, 'admin')
        ->get(route('admin.inventory.index', ['stock' => 'Stok menipis']))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('items.meta.total', 1)
                ->where('items.data.0.id', $short->id)
                ->where('items.data.0.isLowStock', true),
        );
});

test('the module opens on the active items and can be asked for the rest', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    StockItem::factory()->create(['name' => 'Masih Dipakai']);
    StockItem::factory()->create(['name' => 'Sudah Pensiun', 'is_active' => false]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.inventory.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('items.meta.total', 1));

    $this->actingAs($owner, 'admin')
        ->get(route('admin.inventory.index', ['status' => 'Semua']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('items.meta.total', 2));
});

test('search matches the name, the sku and the supplier', function (string $term) {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $match = StockItem::factory()->create([
        'name' => 'Snow Foam pH Netral',
        'sku' => 'SNW-001',
        'supplier' => 'PT Kilau Kimia',
    ]);
    StockItem::factory()->create(['name' => 'Spons Cuci', 'sku' => 'SPN-011', 'supplier' => 'Toko Lain']);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.inventory.index', ['q' => $term]))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('items.meta.total', 1)
                ->where('items.data.0.id', $match->id),
        );
})->with(['Snow Foam', 'SNW-001', 'Kilau']);

test('staff without create access cannot add an item or record a movement', function () {
    $staff = inventoryStaff(['read' => true, 'update' => true]);
    $item = StockItem::factory()->create(['quantity' => 5]);

    $this->actingAs($staff, 'admin')
        ->post(route('admin.inventory.store'), stockItemPayload())
        ->assertForbidden();

    $this->actingAs($staff, 'admin')
        ->post(route('admin.inventory.movements.store', $item), ['type' => 'masuk', 'quantity' => 1])
        ->assertForbidden();

    expect(StockItem::query()->count())->toBe(1)
        ->and(StockMovement::query()->count())->toBe(0);
});

test('staff without update access cannot edit an item or flip its status', function () {
    $staff = inventoryStaff(['read' => true, 'create' => true]);
    $item = StockItem::factory()->create();

    $this->actingAs($staff, 'admin')
        ->patch(route('admin.inventory.update', $item), stockItemPayload(['sku' => 'NEW-001']))
        ->assertForbidden();

    $this->actingAs($staff, 'admin')
        ->patch(route('admin.inventory.status.update', $item), ['is_active' => false])
        ->assertForbidden();

    expect($item->fresh()->is_active)->toBeTrue();
});

test('a staff page reports only the abilities the role actually holds', function () {
    $staff = inventoryStaff(['read' => true]);

    $this->actingAs($staff, 'admin')
        ->get(route('admin.inventory.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('capabilities.create', false)
                ->where('capabilities.update', false),
        );
});
