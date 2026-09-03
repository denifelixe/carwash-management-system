<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Support\Admin\OperationalDataWindow;
use Illuminate\Support\Facades\DB;

class DeleteOrder
{
    public function __construct(private RecalculateDailyBalances $recalculateDailyBalances) {}

    public function handle(Order $order, Admin $admin): string
    {
        return DB::transaction(function () use ($order, $admin): string {
            $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            OperationalDataWindow::ensureOrderCanBeDeleted($order);
            $transactions = OrderTransaction::query()
                ->whereBelongsTo($order)
                ->orderBy('paid_at')
                ->lockForUpdate()
                ->get();
            $serviceDate = $order->service_date->toDateString();
            $firstPaymentDate = $transactions->first()?->paid_at->toDateString();

            $transactions->each(function (OrderTransaction $transaction) use ($admin): void {
                $transaction->update(['deleted_by_admin_id' => $admin->getKey()]);
                $transaction->delete();
            });
            $order->update(['deleted_by_admin_id' => $admin->getKey()]);
            $order->delete();

            if ($firstPaymentDate !== null) {
                $this->recalculateDailyBalances->handle($firstPaymentDate);
            }

            return $serviceDate;
        });
    }
}
