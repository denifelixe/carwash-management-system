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

class SaveBooking
{
    public function __construct(private CaptureOrderLead $captureOrderLead) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, int $adminId, ?Order $booking = null): Order
    {
        return DB::transaction(function () use ($data, $adminId, $booking): Order {
            if ($booking !== null) {
                $booking = Order::query()->lockForUpdate()->findOrFail($booking->id);
                OperationalDataWindow::ensureAllows($booking->service_date);

                abort_if(
                    $booking->source !== 'booking' || $booking->status !== 'booking',
                    422,
                    'Booking yang sudah diproses tidak dapat diubah.',
                );
            }

            $quantities = collect($data['items'])->mapWithKeys(
                fn (array $item): array => [(int) $item['service_variation_id'] => (int) $item['quantity']],
            );
            $servicesAreLocked = false;

            if ($booking !== null) {
                $existingQuantities = $booking->serviceVariations()->get()
                    ->mapWithKeys(fn (ServiceVariation $variation): array => [
                        $variation->id => (int) $variation->pivot->quantity,
                    ]);
                $servicesAreLocked = $booking->transactions()->exists();

                if ($servicesAreLocked && $quantities->sortKeys()->all() !== $existingQuantities->sortKeys()->all()) {
                    throw ValidationException::withMessages([
                        'items' => 'Layanan tidak dapat diubah karena booking sudah memiliki transaksi.',
                    ]);
                }
            }

            /** @var Collection<int, ServiceVariation> $variations */
            $variations = ServiceVariation::query()->with('service')->whereKey($quantities->keys())
                ->lockForUpdate()->get();

            abort_if($variations->count() !== $quantities->count(), 422, 'Pilihan layanan tidak lagi tersedia.');
            $existingVariationIds = $booking?->serviceVariations()->pluck('service_variations.id')->all() ?? [];
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
            /*
             * A booking taken for a non-member is still that person's first
             * footprint at the outlet, so it files a lead exactly like a
             * walk-in does.
             */
            $lead = $member === null
                ? $this->captureOrderLead->handle([
                    'name' => $customerName,
                    'phone' => $customerPhone,
                    'vehicle_name' => $vehicleName,
                    'vehicle_plate' => $vehiclePlate,
                ])
                : null;
            $subtotal = $servicesAreLocked
                ? (int) $booking->subtotal
                : (int) $variations->sum(
                    fn (ServiceVariation $variation): int => $variation->price * $quantities[$variation->id],
                );
            $discount = (int) ($booking?->discount ?? 0);

            $values = [
                'member_id' => $member?->id,
                'member_vehicle_id' => $vehicle?->id,
                'lead_id' => $lead?->id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'vehicle_name' => $vehicleName,
                'vehicle_plate' => $vehiclePlate,
                'service_date' => $data['service_date'],
                'source' => 'booking',
                'status' => 'booking',
                'subtotal' => $subtotal,
                'total' => $servicesAreLocked ? (int) $booking->total : max(0, $subtotal - $discount),
                'stamps_earned' => $servicesAreLocked
                    ? (int) $booking->stamps_earned
                    : ($member === null ? 0 : (int) $variations->sum(
                        fn (ServiceVariation $variation): int => $variation->service->stamps * $quantities[$variation->id],
                    )),
            ];

            if ($booking === null) {
                $booking = Order::query()->create([
                    ...$values,
                    'number' => 'ORD-BK-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                    'created_by_admin_id' => $adminId,
                    'arrived_at' => null,
                    'booking_date' => now()->toDateString(),
                ]);
            } else {
                $booking->update($values);
            }

            if (! $servicesAreLocked) {
                $booking->serviceVariations()->sync($variations->mapWithKeys(fn (ServiceVariation $variation): array => [
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
            }

            return $booking;
        }, attempts: 3);
    }
}
