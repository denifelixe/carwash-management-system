<?php

namespace App\Support\Admin;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Support\Demo\DateFilter;

/**
 * One payload shape for stock items and their movements, so the live module
 * and the demo console render the same rows through the same page.
 */
class StockPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function item(StockItem $item): array
    {
        return [
            ...self::itemOption($item),
            'category' => $item->category,
            'unitCost' => $item->unit_cost,
            'stockValue' => $item->stockValue(),
            'supplier' => $item->supplier ?? '',
            'notes' => $item->notes ?? '',
            'isActive' => $item->is_active,
            'updatedAt' => $item->updated_at !== null
                ? DateFilter::format($item->updated_at->toDateString())
                : '—',
        ];
    }

    /**
     * The half of an item the movement dialog needs: enough to name the row and
     * to work out what the stock becomes.
     *
     * @return array<string, mixed>
     */
    public static function itemOption(StockItem $item): array
    {
        return [
            'id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'unit' => $item->unit,
            'quantity' => $item->quantity,
            'minQuantity' => $item->min_quantity,
            'isLowStock' => $item->isLowStock(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function movement(StockMovement $movement): array
    {
        $item = $movement->relationLoaded('stockItem') ? $movement->stockItem : null;
        $recordedBy = $movement->relationLoaded('recordedBy') ? $movement->recordedBy : null;

        return [
            'id' => $movement->id,
            'itemId' => $movement->stock_item_id,
            'item' => $item?->name ?? '—',
            'sku' => $item?->sku ?? '—',
            'unit' => $item?->unit ?? '',
            'type' => $movement->type,
            'quantity' => $movement->quantity,
            'quantityAfter' => $movement->quantity_after,
            'note' => ($movement->note ?? '') !== '' ? $movement->note : '—',
            'date' => DateFilter::format($movement->recorded_at->toDateString()),
            'time' => $movement->recorded_at->format('H.i'),
            'by' => $recordedBy?->name ?? 'Sistem',
        ];
    }
}
