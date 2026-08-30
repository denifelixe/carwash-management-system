<?php

namespace Database\Factories;

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
}
