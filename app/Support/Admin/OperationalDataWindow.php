<?php

namespace App\Support\Admin;

use App\Models\Order;
use App\Models\OrderTransaction;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class OperationalDataWindow
{
    public const MAX_AGE_DAYS = 30;

    public static function allows(CarbonInterface|string $date): bool
    {
        return CarbonImmutable::parse($date)
            ->startOfDay()
            ->greaterThanOrEqualTo(self::cutoff());
    }

    public static function ensureAllows(CarbonInterface|string $date): void
    {
        abort_unless(
            self::allows($date),
            422,
            'Data lebih dari 30 hari tidak dapat diubah atau dihapus.',
        );
    }

    public static function orderCanBeDeleted(Order $order): bool
    {
        if (! self::allows($order->service_date)) {
            return false;
        }

        if ($order->relationLoaded('transactions')) {
            return $order->transactions->every(
                fn (OrderTransaction $transaction): bool => self::allows($transaction->paid_at),
            );
        }

        return ! $order->transactions()->where('paid_at', '<', self::cutoff())->exists();
    }

    public static function ensureOrderCanBeDeleted(Order $order): void
    {
        abort_unless(
            self::orderCanBeDeleted($order),
            422,
            'Order tidak dapat dihapus karena order atau transaksinya sudah lebih dari 30 hari.',
        );
    }

    public static function cutoff(): CarbonImmutable
    {
        return CarbonImmutable::today()->subDays(self::MAX_AGE_DAYS);
    }
}
