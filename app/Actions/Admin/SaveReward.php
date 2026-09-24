<?php

namespace App\Actions\Admin;

use App\Models\Reward;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates and edits rewards with the variation discounts they offer.
 */
class SaveReward
{
    /**
     * @param  array{name: string, description: string|null, icon: string, category: string, required_stamps: int, stock: int, is_active: bool, variation_discounts: list<array{service_variation_id: int, quantity: int, discount_percent: int}>}  $data
     */
    public function handle(array $data, ?Reward $reward = null): Reward
    {
        return DB::transaction(function () use ($data, $reward): Reward {
            $variationDiscounts = Arr::pull($data, 'variation_discounts');

            if ($reward instanceof Reward) {
                $reward->update($data);
            } else {
                /** @var Reward $reward */
                $reward = Reward::query()->create($data);
            }

            $reward->serviceVariations()->sync(collect($variationDiscounts)->mapWithKeys(
                fn (array $row): array => [
                    $row['service_variation_id'] => [
                        'quantity' => $row['quantity'],
                        'discount_percent' => $row['discount_percent'],
                    ],
                ],
            )->all());

            return $reward;
        });
    }
}
