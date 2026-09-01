<?php

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/** A settled order carrying one payment taken by the given cashier just now. */
function paidOrder(Admin $cashier, int $amount = 150000): Order
{
    $service = Service::factory()->create(['name' => 'Cuci Mobil', 'price' => $amount]);
    $order = Order::factory()->create([
        'total' => $amount,
        'paid_amount' => $amount,
        'status' => 'selesai',
        'service_date' => now()->toDateString(),
    ]);
    $variation = $service->serviceVariations()->firstOrFail();

    $order->serviceVariations()->attach($variation->id, [
        'service_name' => $service->name,
        'variations' => null,
        'unit_price' => $service->price,
        'quantity' => 1,
        'total_price' => $service->price,
        'stamps' => 1,
    ]);

    OrderTransaction::factory()->withDailyBalance()->create([
        'order_id' => $order->id,
        'recorded_by_admin_id' => $cashier->id,
        'reference' => $order->number.'-TRX-1',
        'type' => 'Pembayaran Lunas',
        'shift_name' => 'Shift Pagi',
        'amount' => $amount,
        'channel_breakdown' => [['label' => 'Tunai', 'amount' => $amount]],
        'paid_at' => now(),
    ]);

    return $order;
}
