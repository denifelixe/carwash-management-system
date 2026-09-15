<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates and edits stock items.
 *
 * It never writes `quantity` on an edit: on hand only ever moves through
 * RecordStockMovement, so the log always explains the figure. The opening
 * stock a new item is created with is therefore filed as a real movement too.
 */
class SaveStockItem
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?StockItem $item = null, ?Admin $admin = null): StockItem
    {
        return DB::transaction(function () use ($data, $item, $admin): StockItem {
            $openingStock = (int) Arr::pull($data, 'quantity', 0);

            if ($item instanceof StockItem) {
                $item->update($data);

                return $item;
            }

            /** @var StockItem $item */
            $item = StockItem::query()->create([...$data, 'quantity' => 0]);

            if ($openingStock > 0) {
                StockMovement::query()->create([
                    'stock_item_id' => $item->id,
                    'type' => 'masuk',
                    'quantity' => $openingStock,
                    'quantity_before' => 0,
                    'quantity_after' => $openingStock,
                    'unit_cost' => $item->unit_cost,
                    'note' => 'Stok awal',
                    'recorded_by_admin_id' => $admin?->id,
                    'recorded_at' => now(),
                ]);

                $item->update(['quantity' => $openingStock]);
            }

            return $item;
        });
    }
}
