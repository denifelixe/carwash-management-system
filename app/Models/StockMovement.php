<?php

namespace App\Models;

use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One stock in, out, or correction (BR-09).
 *
 * The row keeps the on-hand figure from either side of itself, so the history
 * reads the same a year later even if the item has been edited since.
 *
 * @property int $id
 * @property int $stock_item_id
 * @property string $type
 * @property int $quantity
 * @property int $quantity_before
 * @property int $quantity_after
 * @property int $unit_cost
 * @property string|null $note
 * @property int|null $recorded_by_admin_id
 * @property Carbon $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['stock_item_id', 'type', 'quantity', 'quantity_before', 'quantity_after', 'unit_cost', 'note', 'recorded_by_admin_id', 'recorded_at'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    /** @return BelongsTo<StockItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by_admin_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'unit_cost' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }
}
