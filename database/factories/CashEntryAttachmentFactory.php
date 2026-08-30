<?php

namespace Database\Factories;

use App\Models\CashEntry;
use App\Models\CashEntryAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashEntryAttachment>
 */
class CashEntryAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_entry_id' => CashEntry::factory(),
            'disk' => (string) config('filesystems.default'),
            'path' => 'finance-attachments/'.fake()->uuid().'.jpg',
            'original_name' => 'nota-supplier.jpg',
            'size' => 412000,
        ];
    }
}
