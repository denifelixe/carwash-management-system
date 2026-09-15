<?php

namespace App\Models;

use Database\Factories\StockItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An operational supply the outlet keeps on hand (BR-09).
 *
 * `quantity` is a denormalised on-hand figure. Only RecordStockMovement may
 * write it, and only under a row lock — never assign it from a controller,
 * a form request, or SaveStockItem.
 *
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property string $category
 * @property string $unit
 * @property int $quantity
 * @property int $min_quantity
 * @property int $unit_cost
 * @property string|null $supplier
 * @property string|null $notes
 * @property bool $is_active
 * @property-read int|null $movements_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['sku', 'name', 'category', 'unit', 'quantity', 'min_quantity', 'unit_cost', 'supplier', 'notes', 'is_active'])]
class StockItem extends Model
{
    /** @use HasFactory<StockItemFactory> */
    use HasFactory;

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * At or below the reorder point, which is what turns the row red and files
     * the item under the "Stok menipis" filter.
     */
    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_quantity;
    }

    public function stockValue(): int
    {
        return max($this->quantity, 0) * $this->unit_cost;
    }

    /**
     * The SKU is this record's identity, so it is stored canonically rather
     * than as it was typed; the unique index then catches the same item twice.
     *
     * @return Attribute<never, string>
     */
    protected function sku(): Attribute
    {
        return Attribute::set(fn (?string $sku): string => Str::upper(Str::squish((string) $sku)));
    }

    /**
     * @param  Builder<StockItem>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'min_quantity' => 'integer',
            'unit_cost' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
