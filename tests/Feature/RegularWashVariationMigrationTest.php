<?php

use App\Models\Admin;
use App\Models\CashEntry;
use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Service;
use App\Models\ServiceVariation;
use App\Support\Admin\OrderPresenter;
use App\Support\Admin\ReportQueries;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

function regularWashMigration(): Migration
{
    return require database_path('migrations/2026_09_24_150758_consolidate_regular_wash_into_variations.php');
}

beforeEach(function (): void {
    if (! Schema::hasTable('reward_service')) {
        Schema::create('reward_service', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reward_id');
            $table->foreignId('service_id');
        });
    }
});

/** @return array{Service, Service} */
function regularWashPair(): array
{
    return [
        Service::factory()->create([
            'name' => 'Regular Wash', 'category' => 'Cuci Mobil', 'category_group' => 'Cuci',
            'price' => 60000, 'stamps' => 1, 'sort_order' => 2, 'is_popular' => true,
        ]),
        Service::factory()->create([
            'name' => 'Regular Wash (Large)', 'category' => 'Cuci Mobil', 'category_group' => 'Cuci',
            'price' => 70000, 'stamps' => 1, 'sort_order' => 3,
        ]),
    ];
}

/**
 * @param  list<string>  $tables
 * @return array<string, list<array<string, mixed>>>
 */
function regularWashSnapshot(array $tables): array
{
    return collect($tables)->mapWithKeys(fn (string $table): array => [
        $table => DB::table($table)->get()
            ->map(fn (object $row): array => (array) $row)
            ->sortBy(fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR))
            ->values()->all(),
    ])->all();
}

test('regular wash consolidation retains variation identities prices and parent metadata', function () {
    [$regular, $large] = regularWashPair();
    $standardVariation = $regular->serviceVariations()->sole();
    $largeVariation = $large->serviceVariations()->sole();
    $otherService = Service::factory()->create(['name' => 'Express Wash']);
    $otherVariation = $otherService->serviceVariations()->sole();
    $otherAttributes = [$otherService->refresh()->getAttributes(), $otherVariation->refresh()->getAttributes()];
    $regularAttributes = $regular->getAttributes();

    $standardVariation->update(['price' => 61000]);
    $largeVariation->update(['price' => 72000]);

    regularWashMigration()->up();

    expect($regular->refresh()->variations)->toBe(['Ukuran' => ['Standard', 'Large']])
        ->and($regular->only(['name', 'category', 'category_group', 'stamps', 'icon', 'description', 'is_popular', 'sort_order']))
        ->toEqual(collect($regularAttributes)->only(['name', 'category', 'category_group', 'stamps', 'icon', 'description', 'is_popular', 'sort_order'])->all())
        ->and($standardVariation->refresh()->service_id)->toBe($regular->id)
        ->and($standardVariation->variations)->toBe(['Ukuran' => 'Standard'])
        ->and($standardVariation->price)->toBe(61000)
        ->and($largeVariation->refresh()->service_id)->toBe($regular->id)
        ->and($largeVariation->variations)->toBe(['Ukuran' => 'Large'])
        ->and($largeVariation->price)->toBe(72000)
        ->and([$otherService->refresh()->getAttributes(), $otherVariation->refresh()->getAttributes()])->toBe($otherAttributes);

    $this->assertModelMissing($large);
    $this->assertDatabaseCount('service_variations', 3);
});

test('regular wash consolidation preserves historical records receipts and sales reports', function (string $status, int $paidAmount, bool $deleted) {
    [$regular, $large] = regularWashPair();
    $order = Order::factory()->for(Member::factory())->create([
        'status' => $status, 'subtotal' => 165000, 'discount' => 5000,
        'total' => 160000, 'paid_amount' => $paidAmount, 'stamps_earned' => 5,
        'booking_date' => $status === 'booking' ? now()->toDateString() : null,
    ]);

    foreach ([[$regular, 50000, 2, 2], [$large, 65000, 1, 1]] as [$service, $price, $quantity, $stamps]) {
        $order->serviceVariations()->attach($service->serviceVariations()->sole(), [
            'service_name' => $service->name, 'variations' => null,
            'unit_price' => $price, 'quantity' => $quantity,
            'total_price' => $price * $quantity, 'stamps' => $stamps,
        ]);
    }

    $transaction = $paidAmount > 0 ? OrderTransaction::factory()->for($order)->withDailyBalance()->create([
        'type' => $status === 'selesai' ? 'Pembayaran Lunas' : 'Pembayaran Sebagian',
        'amount' => $paidAmount,
        'channel_breakdown' => [['label' => 'Tunai', 'amount' => $paidAmount]],
    ]) : null;
    CashEntry::factory()->withDailyBalance()->create();
    RewardRedemption::factory()->create();

    if ($deleted) {
        $order->delete();
    }

    $tables = ['orders', 'order_services', 'order_transactions', 'cash_entries', 'daily_balance', 'reward_redemptions', 'rewards', 'members'];
    $before = regularWashSnapshot($tables);
    $receipt = $transaction !== null && ! $deleted ? OrderPresenter::receipt($transaction->fresh()) : null;
    $today = CarbonImmutable::today();
    $sales = ReportQueries::itemSales($today, $today);

    regularWashMigration()->up();

    expect(regularWashSnapshot($tables))->toBe($before)
        ->and(ReportQueries::itemSales($today, $today))->toBe($sales)
        ->and($order->serviceVariations()->count())->toBe(2);

    if ($receipt !== null) {
        expect(OrderPresenter::receipt($transaction->fresh()))->toBe($receipt)
            ->and($receipt['lines'])->toBe([
                ['name' => 'Regular Wash x2', 'price' => 100000],
                ['name' => 'Regular Wash (Large)', 'price' => 65000],
            ]);
    }
})->with([
    'settled' => ['selesai', 160000, false],
    'partially paid' => ['proses', 25000, false],
    'booking' => ['booking', 0, false],
    'deleted settled order' => ['selesai', 160000, true],
]);

test('the merged regular wash catalog supports ordering both sizes at their existing prices', function () {
    [$regular, $large] = regularWashPair();
    $standardId = $regular->serviceVariations()->sole()->id;
    $largeId = $large->serviceVariations()->sole()->id;
    $owner = Admin::factory()->create(['is_owner' => true]);
    $member = Member::factory()->create();
    $vehicle = MemberVehicle::factory()->for($member)->create();

    regularWashMigration()->up();

    $this->actingAs($owner, 'admin')->get(route('admin.master.services.index'))
        ->assertSuccessful()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('services', 1)
        ->where('services.0.name', 'Regular Wash')
        ->where('services.0.variations', ['Ukuran' => ['Standard', 'Large']])
        ->has('services.0.service_variations', 2));

    $this->get(route('admin.orders.index'))->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('services', 1)
            ->where('services.0.name', 'Regular Wash')
            ->where('services.0.variations', ['Ukuran' => ['Standard', 'Large']])
            ->has('services.0.serviceVariations', 2));

    $this->post(route('admin.orders.store'), [
        'customer_mode' => 'existing', 'member_id' => $member->id, 'member_vehicle_id' => $vehicle->id,
        'items' => [
            ['service_variation_id' => $standardId, 'quantity' => 2],
            ['service_variation_id' => $largeId, 'quantity' => 1],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $order = Order::query()->latest('id')->firstOrFail();
    expect($order->subtotal)->toBe(190000)
        ->and($order->stamps_earned)->toBe(3)
        ->and($order->serviceVariations->pluck('pivot.service_name')->all())->toBe(['Regular Wash', 'Regular Wash'])
        ->and($order->serviceVariations->map(fn (ServiceVariation $variation): array => json_decode($variation->pivot->variations, true))->all())
        ->toBe([['Ukuran' => 'Standard'], ['Ukuran' => 'Large']]);
});

test('regular wash consolidation is idempotent and skips a missing pair', function () {
    regularWashMigration()->up();
    [$regular, $large] = regularWashPair();
    regularWashMigration()->up();
    $before = regularWashSnapshot(['services', 'service_variations']);
    $this->travel(1)->days();

    regularWashMigration()->up();

    expect(regularWashSnapshot(['services', 'service_variations']))->toBe($before);
    $this->assertModelMissing($large);
    $this->assertModelExists($regular);
});

test('regular wash consolidation preserves which sizes are sellable', function (bool $regularActive, bool $largeActive, bool $largeVariationActive) {
    [$regular, $large] = regularWashPair();
    $regular->update(['is_active' => $regularActive]);
    $large->update(['is_active' => $largeActive]);
    $large->serviceVariations()->sole()->update(['is_active' => $largeVariationActive]);
    $standardId = $regular->serviceVariations()->sole()->id;
    $largeId = $large->serviceVariations()->sole()->id;

    regularWashMigration()->up();

    expect($regular->refresh()->is_active)->toBe($regularActive || $largeActive)
        ->and(ServiceVariation::findOrFail($standardId)->is_active)->toBe($regularActive)
        ->and(ServiceVariation::findOrFail($largeId)->is_active)->toBe($largeActive && $largeVariationActive);
})->with([[false, true, true], [true, false, true], [true, true, false], [false, false, true]]);

test('regular wash conflicts abort without changing catalog or linked records', function (Closure $conflict, string $message) {
    [$regular, $large] = regularWashPair();
    $conflict($regular, $large);
    $tables = ['services', 'service_variations', 'reward_service', 'orders', 'order_services', 'order_transactions'];
    $before = regularWashSnapshot($tables);

    expect(fn () => regularWashMigration()->up())->toThrow(RuntimeException::class, $message);
    expect(regularWashSnapshot($tables))->toBe($before);
})->with([
    'category mismatch' => [fn (Service $regular, Service $large) => $large->update(['category' => 'Cuci Motor']), 'kategori, grup, atau stempel berbeda'],
    'group mismatch' => [fn (Service $regular, Service $large) => $large->update(['category_group' => 'Detailing']), 'kategori, grup, atau stempel berbeda'],
    'stamp mismatch' => [fn (Service $regular, Service $large) => $large->update(['stamps' => 2]), 'kategori, grup, atau stempel berbeda'],
    'existing axis' => [fn (Service $regular, Service $large) => $regular->update(['variations' => ['Ukuran' => ['Small']]]), 'struktur variasi bentrok'],
    'existing selection' => [fn (Service $regular, Service $large) => $large->serviceVariations()->sole()->update(['variations' => ['Ukuran' => 'Large']]), 'struktur variasi bentrok'],
    'extra variation' => [fn (Service $regular, Service $large) => ServiceVariation::factory()->for($regular)->create(), 'struktur variasi bentrok'],
    'missing variation' => [fn (Service $regular, Service $large) => $large->serviceVariations()->delete(), 'struktur variasi bentrok'],
    'regular reward' => [fn (Service $regular, Service $large) => DB::table('reward_service')->insert(['reward_id' => Reward::factory()->create()->id, 'service_id' => $regular->id]), 'terhubung ke reward'],
    'large reward' => [fn (Service $regular, Service $large) => DB::table('reward_service')->insert(['reward_id' => Reward::factory()->create()->id, 'service_id' => $large->id]), 'terhubung ke reward'],
]);

test('regular wash consolidation rolls back catalog writes if a later write fails', function () {
    regularWashPair();
    $before = regularWashSnapshot(['services', 'service_variations']);
    DB::unprepared("CREATE TRIGGER prevent_regular_wash_delete BEFORE DELETE ON services BEGIN SELECT RAISE(ABORT, 'blocked deletion'); END");

    try {
        expect(fn () => regularWashMigration()->up())->toThrow(QueryException::class);
        expect(regularWashSnapshot(['services', 'service_variations']))->toBe($before);
    } finally {
        DB::unprepared('DROP TRIGGER prevent_regular_wash_delete');
    }
});

test('regular wash consolidation explicitly rejects rollback', function () {
    expect(fn () => regularWashMigration()->down())->toThrow(RuntimeException::class, 'migrasi koreksi');
});
