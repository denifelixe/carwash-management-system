<?php

namespace App\Actions\Admin;

use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\ServiceVariation;
use App\Support\Admin\OperationalDataWindow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateOrder
{
    public function __construct(private CaptureOrderLead $captureOrderLead) {}

    /** @param array<string, mixed> $data */
    public function handle(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            OperationalDataWindow::ensureAllows($order->service_date);
            abort_unless(
                $order->isEditable(),
                422,
                'Order yang sudah memiliki transaksi, lunas, atau selesai tidak dapat diubah.',
            );

            $quantities = collect($data['items'])->mapWithKeys(
                fn (array $item): array => [(int) $item['service_variation_id'] => (int) $item['quantity']],
            );
            /** @var Collection<int, ServiceVariation> $variations */
            $variations = ServiceVariation::query()->with('service')->whereKey($quantities->keys())
                ->lockForUpdate()->get();

            abort_if($variations->count() !== $quantities->count(), 422, 'Pilihan layanan tidak lagi tersedia.');
            $existingVariationIds = $order->serviceVariations()->pluck('service_variations.id')->all();
            abort_if(
                $variations->contains(
                    fn (ServiceVariation $variation): bool => (! $variation->is_active || ! $variation->service->is_active)
                        && ! in_array($variation->id, $existingVariationIds, true),
                ),
                422,
                'Pilihan layanan tidak lagi tersedia.',
            );

            $member = null;
            $vehicle = null;

            if ($data['customer_mode'] === 'existing') {
                $member = Member::query()->whereKey((int) $data['member_id'])->firstOrFail();
                $vehicle = MemberVehicle::query()
                    ->whereBelongsTo($member)
                    ->findOrFail((int) $data['member_vehicle_id']);
            }

            $customerName = $member?->name ?? Str::squish($data['customer_name'] ?? '');
            $customerPhone = $member?->phone ?? ($data['customer_phone'] ?? '');
            $vehicleName = $vehicle?->name ?? Str::squish($data['vehicle_name'] ?? '');
            $vehiclePlate = $vehicle?->plate ?? ($data['vehicle_plate'] ?? '');
            $lead = $member === null
                ? $this->captureOrderLead->handle([
                    'name' => $customerName,
                    'phone' => $customerPhone,
                    'vehicle_name' => $vehicleName,
                    'vehicle_plate' => $vehiclePlate,
                ])
                : null;
            $subtotal = (int) $variations->sum(
                fn (ServiceVariation $variation): int => $variation->price * $quantities[$variation->id],
            );
            $total = max(0, $subtotal - (int) $order->discount);

            abort_if(
                $total <= (int) $order->paid_amount,
                422,
                'Total order setelah diedit harus lebih besar dari pembayaran yang sudah diterima.',
            );

            $order->update([
                'member_id' => $member?->id,
                'member_vehicle_id' => $vehicle?->id,
                'lead_id' => $lead?->id,
                'handled_by_admin_id' => $data['handled_by_admin_id'],
                'handled_by' => $data['handled_by'],
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'vehicle_name' => $vehicleName,
                'vehicle_plate' => $vehiclePlate,
                'subtotal' => $subtotal,
                'total' => $total,
                'stamps_earned' => $member === null ? 0 : (int) $variations->sum(
                    fn (ServiceVariation $variation): int => $variation->service->stamps * $quantities[$variation->id],
                ),
            ]);

            $order->serviceVariations()->sync($variations->mapWithKeys(fn (ServiceVariation $variation): array => [
                $variation->id => [
                    'service_name' => $variation->service->name,
                    'variations' => $variation->variations === null
                        ? null
                        : json_encode($variation->variations, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'unit_price' => $variation->price,
                    'quantity' => $quantities[$variation->id],
                    'total_price' => $variation->price * $quantities[$variation->id],
                    'stamps' => $variation->service->stamps,
                ],
            ])->all());

            return $order;
        }, attempts: 3);
    }
}
