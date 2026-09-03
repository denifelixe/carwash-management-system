<?php

namespace App\Actions\Admin;

use App\Models\Lead;
use App\Models\Member;
use App\Support\VehiclePlate;

/**
 * Closes the leads behind a freshly registered member (BR-06).
 *
 * The row is kept rather than deleted: the funnel it records — how many visits
 * it took before this person signed up — is the point of the module. It simply
 * drops out of the working list, which filters on converted_member_id.
 */
class MarkLeadConverted
{
    /**
     * @param  list<string>  $plates
     */
    public function handle(Member $member, array $plates): void
    {
        $normalized = array_values(array_filter(array_map(
            fn (string $plate): string => VehiclePlate::normalize($plate),
            $plates,
        )));

        if ($normalized === []) {
            return;
        }

        Lead::query()
            ->whereIn('vehicle_plate', $normalized)
            ->whereNull('converted_member_id')
            ->update([
                'converted_member_id' => $member->id,
                /* The outlet's own clock, which is what the column holds. */
                'converted_at' => now(),
            ]);
    }
}
