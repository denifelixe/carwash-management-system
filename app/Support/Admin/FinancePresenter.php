<?php

namespace App\Support\Admin;

use App\Models\Admin;
use App\Models\CashEntry;
use App\Models\Order;
use App\Models\OrderTransaction;
use Illuminate\Support\Str;

/**
 * One payload shape for the finance ledger, whether the row was booked by the
 * cashier or written by hand, so the page renders both from the same fields.
 */
class FinancePresenter
{
    /**
     * A payment the cashier accepted, read as money in on the day it was taken.
     *
     * @return array<string, mixed>
     */
    public static function posMoneyIn(OrderTransaction $transaction): array
    {
        /** @var Order $order */
        $order = $transaction->getRelation('order');
        $recordedBy = $transaction->getRelation('recordedBy');
        $paidAt = $transaction->paid_at;
        $time = $paidAt->format('H.i');
        $category = $transaction->type === 'Pembayaran Sebagian'
            ? 'Pembayaran Sebagian/Booking Order'
            : 'Pembayaran Sisa/Lunas (Order Selesai)';

        return [
            /* Prefixed so the page can tell a booked payment from a hand-written
             * row, and can trace it back to its transaction in the order recap. */
            'id' => 'pos-'.$transaction->reference,
            'ref' => FinanceReference::make(
                $transaction->type.' Order',
                $paidAt->toDateString(),
                $transaction->reference,
            ),
            'date' => $paidAt->toDateString(),
            'time' => $time,
            'category' => $category,
            'description' => $order->services->pluck('pivot.service_name')->join(', '),
            'amount' => (int) $transaction->amount,
            'method' => collect($transaction->channel_breakdown)->pluck('label')->join(' + '),
            'channelBreakdown' => $transaction->channel_breakdown,
            'recordedBy' => $recordedBy instanceof Admin ? $recordedBy->name : '—',
            'shift' => $transaction->shift_name,
            'source' => 'pos',
            'orderId' => $order->id,
            'orderNo' => $order->number,
            'customer' => $order->customer_name,
            'vehicle' => $order->vehicle_name,
            'plate' => $order->vehicle_plate,
            'attachment' => null,
            'attachmentUrl' => null,
            'attachmentIsImage' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function cashEntry(CashEntry $entry): array
    {
        $recordedBy = $entry->getRelation('recordedBy');
        $occurredAt = $entry->occurred_at;

        return [
            'id' => $entry->id,
            'ref' => $entry->reference,
            'date' => $entry->entry_date->toDateString(),
            'time' => $occurredAt->format('H.i'),
            'category' => $entry->category,
            'description' => $entry->description,
            'amount' => (int) $entry->amount,
            'method' => $entry->method,
            'channelBreakdown' => [['label' => $entry->method, 'amount' => (int) $entry->amount]],
            'recordedBy' => $recordedBy instanceof Admin ? $recordedBy->name : '—',
            'shift' => $entry->shift_name,
            'source' => 'manual',
            'orderId' => null,
            'orderNo' => null,
            'customer' => null,
            'vehicle' => null,
            'plate' => null,
            'attachment' => $entry->attachment_path === null ? null : [
                'name' => $entry->attachment_name ?? 'lampiran',
                'size' => self::fileSize($entry->attachment_size),
            ],
            'attachmentUrl' => $entry->attachment_path === null
                ? null
                : route('admin.finance.attachment', $entry, absolute: false),
            /* An image opens in the lightbox; anything else is downloaded. */
            'attachmentIsImage' => self::isImage($entry->attachment_path),
        ];
    }

    /** Whether the stored document can be shown rather than handed over. */
    public static function isImage(?string $path): bool
    {
        if ($path === null) {
            return false;
        }

        return in_array(
            Str::lower(pathinfo($path, PATHINFO_EXTENSION)),
            ['jpg', 'jpeg', 'png'],
            true,
        );
    }

    /** Attachment sizes are shown the way the upload dialog reported them. */
    private static function fileSize(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : max(1, (int) round($bytes / 1024)).' KB';
    }
}
