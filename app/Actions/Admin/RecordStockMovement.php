<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of stock_items.quantity.
 *
 * Every change to on hand goes through here so the movement log and the
 * denormalised figure can never disagree, and so two cashiers recording at the
 * same till second cannot both read the same starting quantity.
 */
class RecordStockMovement
{
    /**
     * @param  array{type: string, quantity: int, note?: string|null, unit_cost?: int|null}  $data
     */
    public function handle(StockItem $item, array $data, ?Admin $admin = null): StockMovement
    {
        return DB::transaction(function () use ($item, $data, $admin): StockMovement {
            /** @var StockItem $locked */
            $locked = StockItem::query()->lockForUpdate()->findOrFail($item->id);

            $before = $locked->quantity;
            $delta = self::delta($data['type'], $data['quantity']);
            $after = $before + $delta;
            $unitCost = $data['unit_cost'] ?? null;

            $movement = StockMovement::query()->create([
                'stock_item_id' => $locked->id,
                'type' => $data['type'],
                'quantity' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'unit_cost' => $data['type'] === 'masuk' ? ($unitCost ?? $locked->unit_cost) : 0,
                'note' => ($data['note'] ?? '') !== '' ? $data['note'] : null,
                'recorded_by_admin_id' => $admin?->id,
                'recorded_at' => now(),
            ]);

            $attributes = ['quantity' => $after];

            /* A restock at a new price re-bases what the stock is worth. */
            if ($data['type'] === 'masuk' && $unitCost !== null) {
                $attributes['unit_cost'] = $unitCost;
            }

            $locked->update($attributes);
            $item->forceFill($attributes);

            return $movement;
        });
    }

    /**
     * Ins and outs are always given as positive amounts and get their sign from
     * the type; a correction arrives already signed.
     */
    public static function delta(string $type, int $quantity): int
    {
        return match ($type) {
            'masuk' => abs($quantity),
            'keluar' => -abs($quantity),
            default => $quantity,
        };
    }
}
