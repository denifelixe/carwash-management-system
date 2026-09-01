<?php

namespace Database\Factories;

use App\Actions\Admin\UpdateDailyBalance;
use App\Models\CashEntry;
use App\Models\CashEntryAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashEntry>
 */
class CashEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $occurredAt = now();

        return [
            'direction' => 'in',
            'reference' => 'TRX-PP-'.fake()->unique()->bothify('######'),
            'category' => 'Penjualan Produk',
            'description' => 'Penjualan parfum mobil',
            'amount' => 360000,
            'method' => 'Tunai',
            'shift_name' => 'Shift Pagi',
            'entry_date' => $occurredAt->toDateString(),
            'occurred_at' => $occurredAt,
        ];
    }

    /** Outgoing money always carries its supporting document (BR-10). */
    public function moneyOut(): static
    {
        return $this
            ->state(fn (): array => [
                'direction' => 'out',
                'category' => 'Pembelian Bahan',
                'description' => 'Snow foam 4 galon',
                'amount' => 1280000,
            ])
            ->afterCreating(function (CashEntry $entry): void {
                CashEntryAttachment::factory()->for($entry)->create([
                    'path' => $entry->reference.'/nota-supplier.jpg',
                ]);
            });
    }

    /** Include the derived daily snapshot when a test represents existing production data. */
    public function withDailyBalance(): static
    {
        return $this->afterCreating(function (CashEntry $entry): void {
            $amounts = UpdateDailyBalance::methodAmounts($entry->method, (int) $entry->amount);

            app(UpdateDailyBalance::class)->handle(
                $entry->entry_date->toDateString(),
                cashIncomeDelta: $entry->direction === 'in' ? $amounts['cash'] : 0,
                cashExpenseDelta: $entry->direction === 'out' ? $amounts['cash'] : 0,
                nonCashIncomeDelta: $entry->direction === 'in' ? $amounts['nonCash'] : 0,
                nonCashExpenseDelta: $entry->direction === 'out' ? $amounts['nonCash'] : 0,
            );
        });
    }
}
