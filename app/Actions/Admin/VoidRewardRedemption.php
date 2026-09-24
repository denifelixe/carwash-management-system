<?php

namespace App\Actions\Admin;

use App\Models\Order;
use App\Models\Reward;
use App\Models\RewardRedemption;

class VoidRewardRedemption
{
    /** Return spent stamps and stock while preserving the voided redemption for audit. */
    public function handle(Order $order): void
    {
        $redemption = $order->rewardRedemption()->lockForUpdate()->first();

        if (! $redemption instanceof RewardRedemption) {
            return;
        }

        if ($redemption->reward_id !== null) {
            Reward::query()->whereKey($redemption->reward_id)->increment('stock');
        }

        $redemption->forceFill(['active_slot' => null])->save();
        $redemption->delete();
    }
}
