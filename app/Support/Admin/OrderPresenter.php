<?php

namespace App\Support\Admin;

use App\Models\Admin;
use App\Models\AdminShift;
use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Service;
use App\Support\Demo\Brand;
use App\Support\Demo\DateFilter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * One payload shape for orders, services, and members across the live admin
 * modules, so the order module and the cashier read the same rows.
 */
class OrderPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function booking(Order $booking): array
    {
        return [
            'id' => $booking->id,
            'code' => $booking->number,
            'customerId' => $booking->member_id,
            'customer' => $booking->customer_name,
            'phone' => $booking->customer_phone !== '' ? $booking->customer_phone : 'â€”',
            'vehicle' => $booking->vehicle_name,
            'plate' => $booking->vehicle_plate,
            'service' => $booking->services->pluck('pivot.service_name')->join(', '),
            'serviceIds' => $booking->services->pluck('id')->all(),
            'date' => $booking->service_date->toDateString(),
            'bookingDate' => $booking->booking_date?->toDateString() ?? $booking->created_at?->toDateString(),
            'orderStatus' => $booking->status,
            'estimate' => (int) $booking->total,
            'notes' => $booking->notes ?? 'â€”',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function order(Order $order): array
    {
        $paidAmount = (int) $order->paid_amount;
        $total = (int) $order->total;
        $crew = $order->getRelation('crew');

        return [
            'id' => $order->id,
            'orderNo' => $order->number,
            'invoice' => $order->invoice_number ?? '—',
            'date' => $order->service_date->toDateString(),
            'time' => $order->arrived_at?->format('H.i') ?? '—',
            'bookingDate' => $order->booking_date?->toDateString(),
            'customerId' => $order->member_id,
            'customer' => $order->customer_name.($order->member_id === null ? ' (non-member)' : ''),
            'phone' => $order->customer_phone,
            'vehicle' => $order->vehicle_name,
            'plate' => $order->vehicle_plate,
            'items' => $order->services->pluck('pivot.service_name')->join(', '),
            'serviceIds' => $order->services->pluck('id')->all(),
            'total' => $total,
            'discount' => (int) $order->discount,
            'reward' => $order->reward_name ?? '—',
            'paidAmount' => $paidAmount,
            'payment' => $order->payment_method ?? '—',
            'paymentStatus' => $paidAmount === 0 ? 'belum bayar' : ($paidAmount >= $total ? 'lunas' : 'sebagian'),
            'status' => $order->status,
            'stampsEarned' => (int) $order->stamps_earned,
            'crew' => $crew instanceof Admin ? $crew->name : 'Menunggu crew',
            'bay' => $order->bay ?? '—',
            'source' => $order->source,
            'transactions' => $order->transactions
                ->map(fn (OrderTransaction $transaction): array => self::transaction($transaction, $order))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function transaction(OrderTransaction $transaction, Order $order): array
    {
        $recordedBy = $transaction->getRelation('recordedBy');

        return [
            'id' => $transaction->reference,
            'orderId' => $order->id,
            'date' => $transaction->paid_at->toDateString(),
            'time' => $transaction->paid_at->format('H.i'),
            'type' => $transaction->type,
            'amount' => (int) $transaction->amount,
            'channels' => collect($transaction->channel_breakdown)->pluck('label')->join(' + '),
            'channelBreakdown' => $transaction->channel_breakdown,
            'recordedBy' => $recordedBy instanceof Admin ? $recordedBy->name : null,
            'shift' => $transaction->shift_name,
        ];
    }

    /**
     * A work shift as the console addresses it: the key a tab is keyed by, the
     * name a payment is stamped with, and the window it covers.
     *
     * @return array{key: string, name: string, time: string|null}
     */
    public static function workShift(AdminShift $shift): array
    {
        return [
            'key' => $shift->key,
            'name' => $shift->name,
            /* A shift without both ends describes no window to show. */
            'time' => $shift->starts_at !== null && $shift->ends_at !== null
                ? self::clock($shift->starts_at).' - '.self::clock($shift->ends_at)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function service(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'category' => $service->category,
            'price' => (int) $service->price,
            'stamps' => (int) $service->stamps,
            'icon' => $service->icon,
            'description' => $service->description ?? '',
            'popular' => $service->is_popular,
            'isActive' => $service->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function customer(Member $member): array
    {
        $vehicles = $member->vehicles->map(fn (MemberVehicle $vehicle): array => [
            'id' => $vehicle->id,
            'name' => $vehicle->name,
            'plate' => $vehicle->plate,
            'type' => $vehicle->type,
            'isPrimary' => $vehicle->is_primary,
        ]);
        $primaryVehicle = $vehicles->first();
        $stampTarget = (int) Brand::identity()['stampTarget'];
        $lifetimeStamps = (int) $member->getAttribute('stamps_earned_total');
        $lastOrderDate = $member->getAttribute('last_order_date');

        return [
            'id' => $member->id,
            'name' => $member->name,
            'memberId' => 'MEM-'.str_pad((string) $member->id, 6, '0', STR_PAD_LEFT),
            'phone' => $member->phone ?? '',
            'email' => $member->email ?? '',
            'vehicle' => $primaryVehicle['name'] ?? '—',
            'plate' => $primaryVehicle['plate'] ?? '—',
            'vehicles' => $vehicles->all(),
            'stamps' => $stampTarget > 0 ? $lifetimeStamps % $stampTarget : $lifetimeStamps,
            'lifetimeStamps' => $lifetimeStamps,
            'visits' => (int) $member->orders_count,
            'spend' => (int) $member->getAttribute('orders_sum_total'),
            'joinedAt' => $member->created_at?->format('M Y') ?? '',
            'lastVisit' => self::visitLabel($lastOrderDate),
            'initials' => self::initials($member->name),
            'status' => $member->is_active ? 'aktif' : 'tidak aktif',
            'hasAccount' => $member->password !== null,
        ];
    }

    /** Shift windows are stored as times but read as 07.00 on the console. */
    public static function clock(string $time): string
    {
        return Str::of($time)->substr(0, 5)->replace(':', '.')->value();
    }

    public static function initials(string $name): string
    {
        return Str::of($name)
            ->squish()
            ->explode(' ')
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    private static function visitLabel(mixed $date): string
    {
        if (! is_string($date) || $date === '') {
            return '—';
        }

        return DateFilter::format(CarbonImmutable::parse($date)->toDateString());
    }
}
