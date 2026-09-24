<?php

namespace App\Models;

use Database\Factories\RewardRedemptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Stamps a member traded for a reward on one order — the debit side of the
 * stamp wallet. The reward's name, price in stamps, and the discount it gave
 * are copied in, so editing the catalog never rewrites what was redeemed.
 *
 * @property int $id
 * @property int $member_id
 * @property int $order_id
 * @property int|null $reward_id
 * @property int|null $active_slot
 * @property int|null $redeemed_by_admin_id
 * @property string $reward_name
 * @property int $stamps
 * @property int $discount
 * @property Carbon $redeemed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['member_id', 'order_id', 'reward_id', 'redeemed_by_admin_id', 'reward_name', 'stamps', 'discount', 'redeemed_at'])]
class RewardRedemption extends Model
{
    /** @use HasFactory<RewardRedemptionFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    /** @return BelongsTo<Reward, $this> */
    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'redeemed_by_admin_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active_slot' => 'integer',
            'stamps' => 'integer',
            'discount' => 'integer',
            'redeemed_at' => 'datetime',
        ];
    }
}
