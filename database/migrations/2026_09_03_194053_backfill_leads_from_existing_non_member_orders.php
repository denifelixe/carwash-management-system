<?php

use App\Support\VehiclePlate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Files the non-member orders that predate the Leads module into its database.
 *
 * The migration uses the same identity as live capture: one canonical plate is
 * one lead. Existing leads are reused without replacing their newer profile,
 * while leads created here take the latest non-empty details from old orders.
 */

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /** @var array<string, int> $leadIdsByPlate */
        $leadIdsByPlate = [];
        /** @var array<int, true> $createdLeadIds */
        $createdLeadIds = [];

        DB::table('orders')
            ->whereNull('member_id')
            ->whereNull('lead_id')
            ->where('vehicle_plate', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function (Collection $orders) use (&$leadIdsByPlate, &$createdLeadIds): void {
                DB::transaction(function () use ($orders, &$leadIdsByPlate, &$createdLeadIds): void {
                    foreach ($orders as $order) {
                        $plate = VehiclePlate::normalize((string) $order->vehicle_plate);

                        if ($plate === '') {
                            continue;
                        }

                        if (! isset($leadIdsByPlate[$plate])) {
                            $existingLeadId = DB::table('leads')
                                ->where('vehicle_plate', $plate)
                                ->value('id');

                            if ($existingLeadId !== null) {
                                $leadIdsByPlate[$plate] = (int) $existingLeadId;
                            } else {
                                $memberVehicle = DB::table('member_vehicles')
                                    ->where('plate', $plate)
                                    ->first(['member_id', 'created_at']);
                                $seenAt = $order->arrived_at
                                    ?? $order->created_at
                                    ?? $order->service_date.' 00:00:00';
                                $leadId = DB::table('leads')->insertGetId([
                                    ...$this->detailsFrom(
                                        (string) $order->customer_name,
                                        (string) $order->customer_phone,
                                        (string) $order->vehicle_name,
                                        $plate,
                                    ),
                                    'vehicle_plate' => $plate,
                                    'notes' => null,
                                    'is_active' => true,
                                    'converted_member_id' => $memberVehicle?->member_id,
                                    'converted_at' => $memberVehicle?->created_at,
                                    'created_at' => $seenAt,
                                    'updated_at' => $seenAt,
                                ]);

                                $leadIdsByPlate[$plate] = $leadId;
                                $createdLeadIds[$leadId] = true;
                            }
                        }

                        $leadId = $leadIdsByPlate[$plate];

                        if (isset($createdLeadIds[$leadId])) {
                            DB::table('leads')->where('id', $leadId)->update([
                                ...$this->nonEmptyDetailsFrom(
                                    (string) $order->customer_name,
                                    (string) $order->customer_phone,
                                    (string) $order->vehicle_name,
                                ),
                                'updated_at' => $order->arrived_at ?? $order->updated_at ?? $order->created_at,
                            ]);
                        }

                        DB::table('orders')
                            ->where('id', $order->id)
                            ->whereNull('lead_id')
                            ->update(['lead_id' => $leadId]);
                    }
                });
            });
    }

    /**
     * @return array{name: string, phone: string|null, vehicle_name: string|null}
     */
    private function detailsFrom(
        string $customerName,
        string $customerPhone,
        string $vehicleName,
        string $plate,
    ): array {
        $details = $this->nonEmptyDetailsFrom($customerName, $customerPhone, $vehicleName);

        return [
            'name' => $details['name'] ?? $plate,
            'phone' => $details['phone'] ?? null,
            'vehicle_name' => $details['vehicle_name'] ?? null,
        ];
    }

    /**
     * @return array{name?: string, phone?: string, vehicle_name?: string}
     */
    private function nonEmptyDetailsFrom(
        string $customerName,
        string $customerPhone,
        string $vehicleName,
    ): array {
        $details = [];
        $name = Str::squish($customerName);
        $phone = trim($customerPhone);
        $vehicleName = Str::squish($vehicleName);

        if ($name !== '') {
            $details['name'] = $name;
        }

        if ($phone !== '') {
            $details['phone'] = $phone;
        }

        if ($vehicleName !== '') {
            $details['vehicle_name'] = $vehicleName;
        }

        return $details;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
         * The rows cannot be distinguished safely from leads created by the
         * application after deployment, so this historical import is retained.
         */
    }
};
