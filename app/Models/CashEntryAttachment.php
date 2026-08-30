<?php

namespace App\Models;

use Database\Factories\CashEntryAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cash_entry_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property int|null $size
 */
#[Fillable(['cash_entry_id', 'disk', 'path', 'original_name', 'size'])]
class CashEntryAttachment extends Model
{
    /** @use HasFactory<CashEntryAttachmentFactory> */
    use HasFactory;

    /** @return BelongsTo<CashEntry, $this> */
    public function cashEntry(): BelongsTo
    {
        return $this->belongsTo(CashEntry::class);
    }
}
