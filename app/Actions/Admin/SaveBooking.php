<?php

namespace App\Actions\Admin;

use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaveBooking
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, int $adminId, ?Order $booking = null): Order
    {
        return DB::transaction(function () use ($data, $adminId, $booking): Order {
            if ($booking !== null) {
                $booking = Order::query()->lockForUpdate()->findOrFail($booking->id);

                abort_if(
                    $booking->source !== 'booking' || $booking->status !== 'booking',
                    422,
                    'Booking yang sudah diproses tidak dapat diubah.',
                );
            }

            /** @var Collection<int, Service> $services */
            $services = Service::query()->whereKey($data['service_ids'])->lockForUpdate()->get();
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
            $subtotal = (int) $services->sum('price');
            $discount = (int) ($booking?->discount ?? 0);

            $values = [
                'member_id' => $member?->id,
                'member_vehicle_id' => $vehicle?->id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'vehicle_name' => $vehicleName,
                'vehicle_plate' => $vehiclePlate,
                'service_date' => $data['service_date'],
                'source' => 'booking',
                'status' => 'booking',
                'subtotal' => $subtotal,
                'total' => max(0, $subtotal - $discount),
                'stamps_earned' => $member === null ? 0 : (int) $services->sum('stamps'),
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

            $booking->services()->sync($services->mapWithKeys(fn (Service $service): array => [
                $service->id => [
                    'service_name' => $service->name,
                    'unit_price' => $service->price,
                    'stamps' => $service->stamps,
                ],
            ])->all());

            return $booking;
        }, attempts: 3);
    }
}
