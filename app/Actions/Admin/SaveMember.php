<?php

namespace App\Actions\Admin;

use App\Models\Member;
use App\Models\MemberVehicle;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveMember
{
    /**
     * @param  array{name: string, phone: string, email: string|null, vehicles: list<array{id?: int|null, name: string, plate: string, type: string}>}  $data
     */
    public function create(array $data): Member
    {
        return DB::transaction(function () use ($data): Member {
            $member = Member::query()->create(Arr::only($data, ['name', 'phone', 'email']));
            $this->syncVehicles($member, $data['vehicles']);

            return $member->load('vehicles');
        });
    }

    /**
     * @param  array{name: string, phone: string, email: string|null, vehicles: list<array{id?: int|null, name: string, plate: string, type: string}>}  $data
     */
    public function update(Member $member, array $data): Member
    {
        return DB::transaction(function () use ($member, $data): Member {
            $member->update(Arr::only($data, ['name', 'phone', 'email']));
            $this->syncVehicles($member, $data['vehicles']);

            return $member->load('vehicles');
        });
    }

    /**
     * @param  list<array{id?: int|null, name: string, plate: string, type: string}>  $vehicles
     */
    private function syncVehicles(Member $member, array $vehicles): void
    {
        $vehicleIds = collect($vehicles)->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id);

        $member->vehicles()->whereKeyNot($vehicleIds)->delete();

        $member->vehicles()
            ->whereKey($vehicleIds)
            ->get()
            ->each(function (MemberVehicle $vehicle) use ($member): void {
                $vehicle->update(['plate' => "TMP{$member->id}X{$vehicle->id}"]);
            });

        foreach ($vehicles as $index => $vehicleData) {
            $attributes = [
                'name' => $vehicleData['name'],
                'plate' => $vehicleData['plate'],
                'type' => $vehicleData['type'],
                'is_primary' => $index === 0,
            ];
            $vehicleId = $vehicleData['id'] ?? null;

            if ($vehicleId !== null) {
                $member->vehicles()->whereKey($vehicleId)->firstOrFail()->update($attributes);

                continue;
            }

            $member->vehicles()->create($attributes);
        }
    }
}
