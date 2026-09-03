<?php

namespace App\Actions\Admin;

use App\Models\Lead;
use App\Support\VehiclePlate;
use Illuminate\Support\Str;

/**
 * Files the walk-in behind a non-member order as a lead (BR-06).
 *
 * A lead is keyed by its plate and nothing else, so the same car coming back is
 * the same row however the cashier spelled the name that day. That is what lets
 * the order form treat "pick an existing lead" as pure prefill: whether the
 * cashier searched or typed the details from scratch, the plate decides.
 */
class CaptureOrderLead
{
    /**
     * @param  array{name: string, phone: string, vehicle_name: string, vehicle_plate: string}  $details
     */
    public function handle(array $details): ?Lead
    {
        $plate = VehiclePlate::normalize($details['vehicle_plate']);

        if ($plate === '') {
            return null;
        }

        $lead = Lead::query()->firstOrNew(['vehicle_plate' => $plate]);
        $name = Str::squish($details['name']);
        $phone = trim($details['phone']);
        $vehicleName = Str::squish($details['vehicle_name']);

        /*
         * The latest visit is the freshest reading of who this person is, but a
         * field left blank at the till must not wipe what an earlier visit knew.
         */
        $lead->name = $name !== '' ? $name : ($lead->name ?? $plate);
        $lead->phone = $phone !== '' ? $phone : $lead->phone;
        $lead->vehicle_name = $vehicleName !== '' ? $vehicleName : $lead->vehicle_name;
        $lead->save();

        return $lead;
    }
}
