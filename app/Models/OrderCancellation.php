<?php

namespace App\Models;

use Database\Factories\OrderCancellationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string $reason
 * @property string $previous_status
 * @property int|null $cancelled_by_admin_id
 * @property string $cancelled_by_name
 * @property Carbon $cancelled_at
 */
#[Fillable(['order_id', 'reason', 'previous_status', 'cancelled_by_admin_id', 'cancelled_by_name', 'cancelled_at'])]
class OrderCancellation extends Model
{
    /** @use HasFactory<OrderCancellationFactory> */
    use HasFactory;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'cancelled_by_admin_id');
    }

    /** @return HasMany<OrderCancellationPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(OrderCancellationPhoto::class)->orderBy('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cancelled_at' => 'datetime'];
    }
}
