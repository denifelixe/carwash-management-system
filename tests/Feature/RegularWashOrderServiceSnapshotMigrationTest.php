<?php

use App\Models\CashEntry;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\RewardRedemption;
use App\Models\Service;
use App\Models\ServiceVariation;
use App\Support\Admin\OrderPresenter;
use App\Support\Admin\ReportQueries;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->regular = Service::factory()->create([
        'name' => 'Regular Wash', 'category' => 'Cuci Mobil', 'category_group' => 'Cuci',
        'variations' => ['Ukuran' => ['Standard', 'Large']], 'price' => 60000, 'stamps' => 1,
    ]);
    $this->standard = $this->regular->serviceVariations()->sole();
    $this->standard->update(['variations' => ['Ukuran' => 'Standard']]);
    $this->large = ServiceVariation::factory()->for($this->regular)->create([
        'variations' => ['Ukuran' => 'Large'], 'price' => 70000,
    ]);
    $this->migration = require database_path('migrations/2026_09_24_152645_normalize_regular_wash_order_service_snapshots.php');
});

/** @return array<string, list<array<string, mixed>>> */
function orderServiceNamingProtectedData(): array
{
    return collect([
        'orders', 'order_services', 'order_transactions', 'cash_entries', 'daily_balance',
        'reward_redemptions', 'rewards', 'reward_service_variation', 'members', 'services', 'service_variations',
    ])->mapWithKeys(fn (string $table): array => [
        $table => DB::table($table)->get()->map(function (object $row) use ($table): array {
            $attributes = (array) $row;

            if ($table === 'order_services') {
                unset($attributes['service_name'], $attributes['variations']);
            }

            return $attributes;
        })->all(),
    ])->all();
}

test('regular wash order naming changes only the historical name and variation snapshot', function (string $status, int $paidAmount, bool $deleted) {
    $order = Order::factory()->create([
        'status' => $status, 'subtotal' => 245000, 'discount' => 5000,
        'total' => 240000, 'paid_amount' => $paidAmount, 'stamps_earned' => 4,
    ]);
    foreach ([[$this->standard, 'Regular Wash', 50000, 1], [$this->large, 'Regular Wash (Large)', 65000, 3]] as [$variation, $name, $price, $quantity]) {
        $order->serviceVariations()->attach($variation, [
            'service_name' => $name, 'variations' => null, 'unit_price' => $price,
            'quantity' => $quantity, 'total_price' => $price * $quantity, 'stamps' => 1,
        ]);
    }
    $payment = $paidAmount > 0 ? OrderTransaction::factory()->for($order)->withDailyBalance()->create([
        'type' => $status === 'selesai' ? 'Pembayaran Lunas' : 'Pembayaran Sebagian',
        'amount' => $paidAmount, 'channel_breakdown' => [['label' => 'Tunai', 'amount' => $paidAmount]],
    ]) : null;
    CashEntry::factory()->withDailyBalance()->create();
    RewardRedemption::factory()->create();

    if ($deleted) {
        $order->delete();
    }

    $before = orderServiceNamingProtectedData();
    $receipt = $payment !== null && ! $deleted ? OrderPresenter::receipt($payment->fresh()) : null;

    $this->migration->up();

    expect(orderServiceNamingProtectedData())->toBe($before);
    $items = $order->serviceVariations()->orderBy('service_variations.id')->get();
    expect($items->pluck('pivot.service_name')->all())->toBe(['Regular Wash', 'Regular Wash'])
        ->and($items->map(fn (ServiceVariation $variation): array => json_decode($variation->pivot->variations, true))->all())
        ->toBe([['Ukuran' => 'Standard'], ['Ukuran' => 'Large']]);

    if ($receipt !== null) {
        $expectedLines = [
            ['name' => 'Regular Wash (Ukuran: Standard)', 'price' => 50000],
            ['name' => 'Regular Wash (Ukuran: Large) x3', 'price' => 195000],
        ];
        expect(OrderPresenter::receipt($payment->fresh()))->toBe([
            ...$receipt,
            'items' => collect($expectedLines)->pluck('name')->join(', '),
            'lines' => $expectedLines,
        ]);
    }

    $snapshots = DB::table('order_services')->get()->toJson();
    $this->migration->up();
    expect(DB::table('order_services')->get()->toJson())->toBe($snapshots)
        ->and(orderServiceNamingProtectedData())->toBe($before);
})->with([
    'settled' => ['selesai', 240000, false],
    'partially paid' => ['proses', 20000, false],
    'booking' => ['booking', 0, false],
    'deleted' => ['selesai', 240000, true],
]);

test('legacy and new regular wash purchases share report rows without changing totals', function () {
    foreach ([
        [$this->standard, 'Regular Wash', null, 1],
        [$this->large, 'Regular Wash (Large)', null, 3],
        [$this->large, 'Regular Wash', ['Ukuran' => 'Large'], 1],
    ] as [$variation, $name, $selection, $quantity]) {
        $amount = $variation->price * $quantity;
        $order = Order::factory()->create(['status' => 'selesai', 'subtotal' => $amount, 'total' => $amount, 'paid_amount' => $amount]);
        $order->serviceVariations()->attach($variation, [
            'service_name' => $name, 'variations' => $selection === null ? null : json_encode($selection),
            'unit_price' => $variation->price, 'quantity' => $quantity, 'total_price' => $amount, 'stamps' => 1,
        ]);
        OrderTransaction::factory()->for($order)->create([
            'type' => 'Pembayaran Lunas', 'amount' => $amount,
            'channel_breakdown' => [['label' => 'Tunai', 'amount' => $amount]],
        ]);
    }
    $today = CarbonImmutable::today();
    $before = ReportQueries::itemSales($today, $today);

    $this->migration->up();

    $after = ReportQueries::itemSales($today, $today);
    expect($after['total'])->toBe($before['total'])->toBe(340000)
        ->and($after['quantity'])->toBe($before['quantity'])->toBe(5)
        ->and($after['groups'][0]['items'])->toBe([
            ['name' => 'Regular Wash (Ukuran: Large)', 'category' => 'Cuci Mobil', 'quantity' => 4, 'total' => 280000],
            ['name' => 'Regular Wash (Ukuran: Standard)', 'category' => 'Cuci Mobil', 'quantity' => 1, 'total' => 60000],
        ]);
});

test('regular wash naming leaves other snapshots untouched', function (string $name, ?array $selection, string $catalogSize) {
    $variation = match ($catalogSize) {
        'Standard' => $this->standard,
        'Large' => $this->large,
        default => Service::factory()->create(['name' => 'Other Service'])->serviceVariations()->sole(),
    };
    $order = Order::factory()->create();
    $order->serviceVariations()->attach($variation, [
        'service_name' => $name, 'variations' => $selection === null ? null : json_encode($selection),
        'unit_price' => 10000, 'quantity' => 2, 'total_price' => 20000, 'stamps' => 3,
    ]);
    $before = DB::table('order_services')->get()->toJson();

    $this->migration->up();

    expect(DB::table('order_services')->get()->toJson())->toBe($before);
})->with([
    'already normalized' => ['Regular Wash', ['Ukuran' => 'Large'], 'Large'],
    'explicit historical size' => ['Regular Wash', ['Ukuran' => 'Small'], 'Standard'],
    'another service with the legacy name' => ['Regular Wash (Large)', null, 'Other'],
    'unrelated snapshot name' => ['Custom Wash', null, 'Standard'],
    'mismatched variation identity' => ['Regular Wash (Large)', null, 'Standard'],
]);

test('regular wash naming safely skips a missing or unconsolidated catalog', function () {
    $this->standard->update(['variations' => null]);
    $this->large->update(['variations' => null]);
    $before = orderServiceNamingProtectedData();
    $this->migration->up();
    expect(orderServiceNamingProtectedData())->toBe($before);

    $this->regular->delete();
    $before = orderServiceNamingProtectedData();
    $this->migration->up();
    expect(orderServiceNamingProtectedData())->toBe($before);
});

test('regular wash naming rolls back both sizes if an update fails', function () {
    $order = Order::factory()->create();
    foreach ([[$this->standard, 'Regular Wash'], [$this->large, 'Regular Wash (Large)']] as [$variation, $name]) {
        $order->serviceVariations()->attach($variation, [
            'service_name' => $name, 'unit_price' => 10000, 'quantity' => 1, 'total_price' => 10000, 'stamps' => 1,
        ]);
    }
    $before = DB::table('order_services')->get()->toJson();
    DB::unprepared("CREATE TRIGGER prevent_large_rename BEFORE UPDATE ON order_services WHEN OLD.service_name = 'Regular Wash (Large)' BEGIN SELECT RAISE(ABORT, 'blocked rename'); END");

    try {
        expect(fn () => $this->migration->up())->toThrow(QueryException::class);
        expect(DB::table('order_services')->get()->toJson())->toBe($before);
    } finally {
        DB::unprepared('DROP TRIGGER prevent_large_rename');
    }
});

test('regular wash naming requires a forward correction instead of undoing new snapshots', function () {
    expect(fn () => $this->migration->down())->toThrow(RuntimeException::class, 'migrasi koreksi');
});
