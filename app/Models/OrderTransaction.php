<?php

namespace App\Models;

use Database\Factories\OrderTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $recorded_by_admin_id
 * @property string $reference
 * @property string $type
 * @property string|null $shift_name
 * @property int $amount
 * @property list<array{label: string, amount: int}> $channel_breakdown
 * @property Carbon $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['order_id', 'recorded_by_admin_id', 'reference', 'type', 'shift_name', 'amount', 'channel_breakdown', 'paid_at'])]
class OrderTransaction extends Model
{
    /** @use HasFactory<OrderTransactionFactory> */
    use HasFactory;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
            'channel_breakdown' => 'array',
            'paid_at' => 'datetime',
        ];
    }
}
