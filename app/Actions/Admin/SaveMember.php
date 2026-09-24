<?php

namespace App\Actions\Admin;

use App\Models\Member;
use App\Models\MemberVehicle;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveMember
{
    public function __construct(private MarkLeadConverted $markLeadConverted) {}

    /**
     * @param  array{name: string, phone: string, email: string|null, password?: string|null, vehicles: list<array{id?: int|null, name: string, plate: string, type: string}>}  $data
     */
    public function create(array $data): Member
    {
        return DB::transaction(function () use ($data): Member {
            $member = Member::query()->create(self::attributes($data));
            $this->syncVehicles($member, $data['vehicles']);
            $this->markLeadConverted->handle($member, array_column($data['vehicles'], 'plate'));

            return $member->load('vehicles');
        });
    }

    /**
     * @param  array{name: string, phone: string, email: string|null, password?: string|null, vehicles: list<array{id?: int|null, name: string, plate: string, type: string}>}  $data
     */
    public function update(Member $member, array $data): Member
    {
        return DB::transaction(function () use ($member, $data): Member {
            $member->update(self::attributes($data));
            $this->syncVehicles($member, $data['vehicles']);
            $this->markLeadConverted->handle($member, array_column($data['vehicles'], 'plate'));

            return $member->load('vehicles');
        });
    }

    /**
     * The member's own columns. A password is only written when one was typed,
     * so saving the form without it keeps the current portal login.
     *
     * @param  array{name: string, phone: string, email: string|null, password?: string|null}  $data
     * @return array<string, string|null>
     */
    private static function attributes(array $data): array
    {
        $attributes = Arr::only($data, ['name', 'phone', 'email']);

        if (($data['password'] ?? null) !== null) {
            $attributes['password'] = $data['password'];
        }

        return $attributes;
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
