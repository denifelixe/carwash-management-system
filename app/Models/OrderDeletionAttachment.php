<?php

namespace App\Models;

use Database\Factories\OrderDeletionAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_deletion_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property int $size
 */
#[Fillable(['order_deletion_id', 'disk', 'path', 'original_name', 'size'])]
class OrderDeletionAttachment extends Model
{
    /** @use HasFactory<OrderDeletionAttachmentFactory> */
    use HasFactory;

    /** @return BelongsTo<OrderDeletion, $this> */
    public function deletion(): BelongsTo
    {
        return $this->belongsTo(OrderDeletion::class, 'order_deletion_id');
    }
}
