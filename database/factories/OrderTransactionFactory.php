<?php

namespace Database\Factories;

use App\Actions\Admin\UpdateDailyBalance;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Support\Admin\PaymentChannelBreakdown;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderTransaction>
 */
class OrderTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'reference' => 'TRX-'.fake()->unique()->bothify('########'),
            'type' => 'Pembayaran Sebagian',
            'amount' => 20000,
            'channel_breakdown' => [['label' => 'Tunai', 'amount' => 20000]],
            'paid_at' => now(),
        ];
    }

    /** Include the derived daily snapshot when a test represents existing production data. */
    public function withDailyBalance(): static
    {
        return $this->afterCreating(function (OrderTransaction $transaction): void {
            $amounts = UpdateDailyBalance::channelAmounts(
                PaymentChannelBreakdown::financial(
                    $transaction->channel_breakdown,
                    (int) $transaction->amount,
                ),
            );

            app(UpdateDailyBalance::class)->handle(
                $transaction->paid_at->toDateString(),
                cashIncomeDelta: $amounts['cash'],
                nonCashIncomeDelta: $amounts['nonCash'],
            );
        });
    }
}
