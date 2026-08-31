<?php

namespace App\Support\Admin;

use App\Models\Admin;
use App\Models\AdminShift;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TransactionShiftResolver
{
    public const MODE_FIXED = 'fixed';

    public const MODE_SCHEDULE = 'schedule';

    /**
     * @return Collection<int, AdminShift>
     */
    public function scheduledShifts(): Collection
    {
        return AdminShift::query()
            ->where('is_active', true)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, AdminShift>|null  $shifts
     * @return Collection<int, AdminShift>
     */
    public function matchingShifts(CarbonInterface $at, ?Collection $shifts = null): Collection
    {
        $shifts ??= $this->scheduledShifts();
        $minute = ((int) $at->format('H') * 60) + (int) $at->format('i');

        return $shifts
            ->filter(fn (AdminShift $shift): bool => $shift->is_active
                && $shift->starts_at !== null
                && $shift->ends_at !== null
                && $this->containsMinute($shift, $minute))
            ->values();
    }

    public function resolve(Admin $admin, ?int $selectedShiftId, CarbonInterface $at): ?AdminShift
    {
        if ($admin->shift_mode !== self::MODE_SCHEDULE) {
            $admin->loadMissing('workShift');
            $workShift = $admin->getRelation('workShift');

            return $workShift instanceof AdminShift ? $workShift : null;
        }

        $matches = $this->matchingShifts($at);

        if ($matches->count() <= 1) {
            return $matches->first();
        }

        $selectedShift = $matches->firstWhere('id', $selectedShiftId);

        if (! $selectedShift instanceof AdminShift) {
            throw ValidationException::withMessages([
                'transaction_shift_id' => 'Pilih salah satu shift yang sedang aktif untuk transaksi ini.',
            ]);
        }

        return $selectedShift;
    }

    /**
     * @return array{mode: string, label: string, caption: string, shifts: list<array{id: int, name: string, starts_at: string, ends_at: string, time: string}>}
     */
    public function presentation(Admin $admin, CarbonInterface $at): array
    {
        $shifts = $this->scheduledShifts();
        $matches = $this->matchingShifts($at, $shifts);

        return [
            'mode' => $admin->shift_mode,
            'label' => $this->label($admin, $matches),
            'caption' => $this->caption($admin, $matches),
            'shifts' => array_values($shifts
                ->map(fn (AdminShift $shift): array => [
                    'id' => $shift->id,
                    'name' => $shift->name,
                    'starts_at' => mb_substr((string) $shift->starts_at, 0, 5),
                    'ends_at' => mb_substr((string) $shift->ends_at, 0, 5),
                    'time' => OrderPresenter::clock((string) $shift->starts_at).' - '.OrderPresenter::clock((string) $shift->ends_at),
                ])
                ->all()),
        ];
    }

    /** @param Collection<int, AdminShift> $matches */
    private function label(Admin $admin, Collection $matches): string
    {
        if ($admin->shift_mode !== self::MODE_SCHEDULE) {
            $admin->loadMissing('workShift');
            $workShift = $admin->getRelation('workShift');

            return $workShift instanceof AdminShift ? $workShift->name : 'Tanpa Shift';
        }

        return match ($matches->count()) {
            0 => 'Tanpa Shift',
            1 => $matches->firstOrFail()->name,
            default => 'Pilih saat transaksi',
        };
    }

    /** @param Collection<int, AdminShift> $matches */
    private function caption(Admin $admin, Collection $matches): string
    {
        if ($admin->shift_mode === self::MODE_SCHEDULE && $matches->count() > 1) {
            return $matches->pluck('name')->implode(' & ');
        }

        return $this->label($admin, $matches);
    }

    private function containsMinute(AdminShift $shift, int $minute): bool
    {
        $startsAt = $this->minutes((string) $shift->starts_at);
        $endsAt = $this->minutes((string) $shift->ends_at);

        if ($startsAt === $endsAt) {
            return false;
        }

        if ($startsAt < $endsAt) {
            return $minute >= $startsAt && $minute < $endsAt;
        }

        return $minute >= $startsAt || $minute < $endsAt;
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return ($hour * 60) + $minute;
    }
}
