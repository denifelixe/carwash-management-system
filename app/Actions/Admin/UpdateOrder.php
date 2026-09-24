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
use Illuminate\Validation\ValidationException;

class UpdateOrder
{
    public function __construct(private CaptureOrderLead $captureOrderLead) {}

    /** @param array<string, mixed> $data */
    public function handle(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            OperationalDataWindow::ensureAllows($order->service_date);
            $servicesLocked = $order->transactions()->exists();

            $quantities = collect($data['items'])->mapWithKeys(
                fn (array $item): array => [(int) $item['service_variation_id'] => (int) $item['quantity']],
            );
            if ($servicesLocked) {
                $existingQuantities = $order->serviceVariations()
                    ->pluck('order_services.quantity', 'service_variations.id')
                    ->map(fn (mixed $quantity): int => (int) $quantity);

                if ($quantities->sortKeys()->all() !== $existingQuantities->sortKeys()->all()) {
                    throw ValidationException::withMessages([
                        'items' => 'Layanan dan jumlahnya tidak dapat diubah karena order sudah memiliki transaksi.',
                    ]);
                }
            }

            $member = null;
            $vehicle = null;

            /*
             * Stamps from a settled order are already in the member's wallet,
             * and a reward has already spent them, so the stored customer is
             * kept whatever the form posted. Only the handler (and, with no
             * payment yet, the services) can still change.
             */
            if ($order->isCustomerLocked()) {
                $order->update([
                    'handled_by_admin_id' => $data['handled_by_admin_id'],
                    'handled_by' => $data['handled_by'],
                ]);

                if ($servicesLocked) {
                    return $order;
                }

                $member = $order->member;
                $vehicle = $order->memberVehicle;
                $data = [
                    ...$data,
                    'customer_mode' => $member === null ? 'walk-in' : 'existing',
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'vehicle_name' => $order->vehicle_name,
                    'vehicle_plate' => $order->vehicle_plate,
                ];
            } elseif ($data['customer_mode'] === 'existing') {
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
            ]);

            if ($servicesLocked) {
                /* The services stay, but whether anyone holds their stamps follows the customer. */
                $order->update([
                    'stamps_earned' => $member === null ? 0 : (int) $order->serviceVariations()->get()->sum(
                        fn (ServiceVariation $variation): int => (int) $variation->pivot->getAttribute('stamps') * (int) $variation->pivot->getAttribute('quantity'),
                    ),
                ]);

                return $order;
            }

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
