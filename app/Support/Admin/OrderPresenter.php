<?php

namespace App\Support\Admin;

use App\Models\Admin;
use App\Models\AdminShift;
use App\Models\Lead;
use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Service;
use App\Support\AppSettings;
use App\Support\Demo\Brand;
use App\Support\Demo\DateFilter;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;
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
        $serviceItems = self::serviceItems($booking);

        return [
            'id' => $booking->id,
            'code' => $booking->number,
            'customerId' => $booking->member_id,
            'customer' => $booking->customer_name,
            'phone' => $booking->customer_phone !== '' ? $booking->customer_phone : '—',
            'vehicle' => $booking->vehicle_name,
            'plate' => $booking->vehicle_plate,
            'service' => collect($serviceItems)->pluck('label')->join(', '),
            'serviceItems' => $serviceItems,
            'serviceIds' => collect($serviceItems)->pluck('serviceId')->unique()->values()->all(),
            'date' => $booking->service_date->toDateString(),
            'bookingDate' => $booking->booking_date?->toDateString() ?? $booking->created_at?->toDateString(),
            'orderStatus' => $booking->status,
            'isMutable' => OperationalDataWindow::allows($booking->service_date),
            'isDeletable' => OperationalDataWindow::orderCanBeDeleted($booking),
            'estimate' => (int) $booking->total,
            'notes' => $booking->notes ?? '—',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function order(Order $order): array
    {
        $paidAmount = (int) $order->paid_amount;
        $total = (int) $order->total;
        $createdBy = $order->relationLoaded('createdBy')
            ? $order->getRelation('createdBy')
            : null;
        $inputBy = $createdBy instanceof Admin ? $createdBy->name : null;
        $handledByAdmin = $order->relationLoaded('handledByAdmin')
            ? $order->getRelation('handledByAdmin')
            : null;
        $handledByManual = filled($order->handled_by) ? $order->handled_by : null;
        $crew = $order->getRelation('crew');
        $serviceItems = self::serviceItems($order);

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
            'items' => collect($serviceItems)->pluck('label')->join(', '),
            'serviceItems' => $serviceItems,
            'serviceIds' => collect($serviceItems)->pluck('serviceId')->unique()->values()->all(),
            'total' => $total,
            'discount' => (int) $order->discount,
            'reward' => $order->reward_name ?? '—',
            'paidAmount' => $paidAmount,
            'payment' => $order->payment_method ?? '—',
            'paymentStatus' => $paidAmount === 0 ? 'belum bayar' : ($paidAmount >= $total ? 'lunas' : 'sebagian'),
            'status' => $order->status,
            'isMutable' => OperationalDataWindow::allows($order->service_date),
            'isDeletable' => OperationalDataWindow::orderCanBeDeleted($order),
            'stampsEarned' => (int) $order->stamps_earned,
            'inputBy' => $inputBy,
            'handledByAdminId' => $handledByAdmin instanceof Admin ? $handledByAdmin->id : null,
            'handledByManual' => $handledByManual,
            'handledBy' => $handledByAdmin instanceof Admin
                ? $handledByAdmin->name
                : ($handledByManual ?? $inputBy),
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
        $tenderBreakdown = $transaction->channel_breakdown;
        $financialBreakdown = PaymentChannelBreakdown::financial(
            $tenderBreakdown,
            (int) $transaction->amount,
        );

        return [
            'id' => $transaction->reference,
            'orderId' => $order->id,
            'date' => $transaction->paid_at->toDateString(),
            'time' => $transaction->paid_at->format('H.i'),
            'type' => $transaction->type,
            'isMutable' => OperationalDataWindow::allows($transaction->paid_at),
            'amount' => (int) $transaction->amount,
            'channels' => collect($financialBreakdown)->pluck('label')->join(' + '),
            'channelBreakdown' => $financialBreakdown,
            'tenderBreakdown' => $tenderBreakdown,
            'tenderedAmount' => PaymentChannelBreakdown::tenderedTotal($tenderBreakdown),
            'changeAmount' => PaymentChannelBreakdown::change($tenderBreakdown, (int) $transaction->amount),
            'recordedBy' => $recordedBy instanceof Admin ? $recordedBy->name : null,
            'shift' => $transaction->shift_name,
            'receiptUrl' => URL::signedRoute('receipts.show', $transaction),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function receipt(OrderTransaction $transaction): array
    {
        $order = $transaction->order;
        $transactions = $order->transactions->values();
        $transactionIndex = $transactions->search(
            fn (OrderTransaction $candidate): bool => $candidate->is($transaction),
        );
        $index = $transactionIndex === false ? $transactions->count() - 1 : $transactionIndex;
        $history = $transactions->take($index);
        $previouslyPaid = (int) $history->sum('amount');
        $paidTotal = $previouslyPaid + (int) $transaction->amount;
        $receiptUrl = URL::signedRoute('receipts.show', $transaction);
        $qrCode = new Writer(new ImageRenderer(
            new RendererStyle(196, 2),
            new SvgImageBackEnd,
        ));
        $serviceItems = self::serviceItems($order);

        return [
            'orderNo' => $order->number,
            'invoice' => $order->invoice_number ?? '—',
            'reference' => $transaction->reference,
            'date' => $transaction->paid_at->toDateString(),
            'time' => $transaction->paid_at->format('H.i'),
            'cashier' => $transaction->recordedBy?->name ?? '—',
            'shift' => $transaction->shift_name ?? 'Tanpa Shift',
            'customer' => $order->customer_name,
            'customerStatus' => $order->member_id === null ? 'Non-member' : 'Member',
            'vehicle' => $order->vehicle_name,
            'plate' => $order->vehicle_plate,
            'items' => collect($serviceItems)->pluck('label')->join(', '),
            'lines' => collect($serviceItems)->map(fn (array $item): array => [
                'name' => $item['label'],
                'price' => $item['totalPrice'],
            ])->all(),
            'subtotal' => (int) $order->subtotal,
            'priorDiscount' => 0,
            'rewardDiscount' => 0,
            'cashierDiscount' => (int) $order->discount,
            'total' => (int) $order->total,
            'tenderedTotal' => PaymentChannelBreakdown::tenderedTotal($transaction->channel_breakdown),
            'change' => PaymentChannelBreakdown::change(
                $transaction->channel_breakdown,
                (int) $transaction->amount,
            ),
            'history' => $history->map(fn (OrderTransaction $entry): array => [
                'date' => $entry->paid_at->toDateString(),
                'time' => $entry->paid_at->format('H.i'),
                'type' => $entry->type,
                'channels' => collect($entry->channel_breakdown)->pluck('label')->join(' + '),
                'cashier' => $entry->recordedBy?->name ?? '—',
                'amount' => (int) $entry->amount,
            ])->all(),
            'previouslyPaid' => $previouslyPaid,
            'paidTotal' => $paidTotal,
            'dueAfter' => max((int) $order->total - $paidTotal, 0),
            'isSettled' => $transaction->type === 'Pembayaran Lunas',
            'isReprint' => false,
            'timezone' => AppSettings::timezone(),
            'payment' => collect($transaction->channel_breakdown)->pluck('label')->join(' + '),
            'paymentBreakdown' => collect($transaction->channel_breakdown)->map(fn (array $channel): array => [
                'method' => $channel['label'],
                'amount' => (int) $channel['amount'],
                'provider' => '',
                'reference' => $channel['reference'] ?? '',
            ])->all(),
            'reward' => $order->reward_name ?? '—',
            'publicUrl' => $receiptUrl,
            'verificationQr' => 'data:image/svg+xml;base64,'.base64_encode($qrCode->writeString($receiptUrl)),
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
            'variations' => $service->variations,
            'serviceVariations' => $service->serviceVariations->map(fn ($variation): array => [
                'id' => $variation->id,
                'variations' => $variation->variations,
                'price' => (int) $variation->price,
                'isActive' => $variation->is_active,
            ])->all(),
            'price' => (int) ($service->serviceVariations->where('is_active', true)->min('price')
                ?? $service->serviceVariations->min('price')
                ?? 0),
            'stamps' => (int) $service->stamps,
            'icon' => $service->icon,
            'description' => $service->description ?? '',
            'popular' => $service->is_popular,
            'isActive' => $service->is_active,
        ];
    }

    /** @return list<array{serviceVariationId: int, serviceId: int, serviceName: string, variations: array<string, string>|null, quantity: int, unitPrice: int, totalPrice: int, label: string}> */
    private static function serviceItems(Order $order): array
    {
        return $order->serviceVariations->map(function ($variation): array {
            $pivotVariations = $variation->pivot->variations;
            $variations = is_string($pivotVariations)
                ? json_decode($pivotVariations, true, flags: JSON_THROW_ON_ERROR)
                : $pivotVariations;
            $variationLabel = collect($variations ?? [])->map(
                fn (string $value, string $attribute): string => "$attribute: $value",
            )->join(', ');
            $quantity = (int) $variation->pivot->quantity;
            $name = (string) $variation->pivot->service_name;
            $label = $variationLabel === '' ? $name : "$name ($variationLabel)";

            if ($quantity > 1) {
                $label .= " x$quantity";
            }

            return [
                'serviceVariationId' => $variation->id,
                'serviceId' => $variation->service_id,
                'serviceName' => $name,
                'variations' => $variations,
                'quantity' => $quantity,
                'unitPrice' => (int) $variation->pivot->unit_price,
                'totalPrice' => (int) $variation->pivot->total_price,
                'label' => $label,
            ];
        })->all();
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

    /**
     * A lead as the leads module lists it.
     *
     * @return array<string, mixed>
     */
    public static function lead(Lead $lead): array
    {
        return [
            ...self::leadOption($lead),
            'notes' => $lead->notes ?? '',
            'visits' => (int) $lead->orders_count,
            'spend' => (int) $lead->getAttribute('orders_sum_total'),
            'firstSeen' => $lead->created_at?->format('M Y') ?? '',
            'lastVisit' => self::visitLabel($lead->getAttribute('last_order_date')),
            'initials' => self::initials($lead->name),
            'status' => $lead->is_active ? 'aktif' : 'tidak aktif',
            'isConverted' => $lead->converted_member_id !== null,
            'convertedMemberId' => $lead->converted_member_id,
        ];
    }

    /**
     * The half of a lead the order form's picker needs: enough to recognise the
     * car and to prefill the four walk-in fields.
     *
     * @return array<string, mixed>
     */
    public static function leadOption(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'phone' => $lead->phone ?? '',
            'vehicleName' => $lead->vehicle_name ?? '',
            'vehiclePlate' => $lead->vehicle_plate,
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
