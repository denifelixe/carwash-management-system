<?php

namespace App\Models;

use Database\Factories\OrderDeletionFactory;
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
 * @property int|null $deleted_by_admin_id
 * @property string $deleted_by_name
 * @property Carbon $deleted_at
 */
#[Fillable(['order_id', 'reason', 'previous_status', 'deleted_by_admin_id', 'deleted_by_name', 'deleted_at'])]
class OrderDeletion extends Model
{
    /** @use HasFactory<OrderDeletionFactory> */
    use HasFactory;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    /** @return BelongsTo<Admin, $this> */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'deleted_by_admin_id');
    }

    /** @return HasMany<OrderDeletionAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(OrderDeletionAttachment::class)->orderBy('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['deleted_at' => 'datetime'];
    }
}
