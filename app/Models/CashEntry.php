<?php

namespace App\Models;

use Database\Factories\CashEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One hand-recorded cash movement (BR-10). Payments taken by the cashier are
 * not repeated here: they are read straight from their order transaction.
 *
 * @property int $id
 * @property string $direction
 * @property string $reference
 * @property string $category
 * @property string $description
 * @property int $amount
 * @property string $method
 * @property int|null $recorded_by_admin_id
 * @property string|null $shift_name
 * @property Carbon $entry_date
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'direction',
    'reference',
    'category',
    'description',
    'amount',
    'method',
    'recorded_by_admin_id',
    'shift_name',
    'entry_date',
    'occurred_at',
])]
class CashEntry extends Model
{
    /** @use HasFactory<CashEntryFactory> */
    use HasFactory;

    /** @return BelongsTo<Admin, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by_admin_id');
    }

    /** @return HasMany<CashEntryAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(CashEntryAttachment::class)->oldest('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'occurred_at' => 'datetime',
        ];
    }
}
