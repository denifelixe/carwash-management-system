<?php

namespace App\Models;

use Database\Factories\OrderCancellationPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_cancellation_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property int $size
 */
#[Fillable(['order_cancellation_id', 'disk', 'path', 'original_name', 'size'])]
class OrderCancellationPhoto extends Model
{
    /** @use HasFactory<OrderCancellationPhotoFactory> */
    use HasFactory;

    /** @return BelongsTo<OrderCancellation, $this> */
    public function cancellation(): BelongsTo
    {
        return $this->belongsTo(OrderCancellation::class, 'order_cancellation_id');
    }
}
