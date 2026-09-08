<?php

namespace App\Support\Admin;

use App\Models\Admin;
use App\Models\AdminShift;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class TransactionShiftResolver
{
    public const MODE_FIXED = 'fixed';

    public const MODE_SCHEDULE = 'schedule';

    public function captureLogin(Admin $admin): void
    {
        Session::put('transaction_shift', [
            'admin_id' => $admin->id,
            'confirmed' => false,
            'selected_shift_id' => null,
            'shifts' => $this->matchingShifts(now())
                ->map(fn (AdminShift $shift): array => $shift->only(['id', 'name', 'starts_at', 'ends_at', 'is_active']))
                ->all(),
        ]);
    }

    /** @return Collection<int, AdminShift> */
    private function loginShifts(Admin $admin): Collection
    {
        if (Session::get('transaction_shift.admin_id') !== $admin->id) {
            $this->captureLogin($admin);
        }

        /** @var list<array{id: int, name: string, starts_at: string, ends_at: string, is_active: bool}> $shifts */
        $shifts = Session::get('transaction_shift.shifts', []);

        return new Collection(array_map(
            fn (array $shift): AdminShift => (new AdminShift)->newFromBuilder($shift),
            $shifts,
        ));
    }

    public function confirmLogin(Admin $admin, ?int $shiftId): void
    {
        $matches = $this->loginShifts($admin);

        if (Session::get('transaction_shift.confirmed', false)) {
            return;
        }

        if ($admin->shift_mode === self::MODE_SCHEDULE && $matches->count() > 1) {
            if (! $matches->contains('id', $shiftId)) {
                throw ValidationException::withMessages([
                    'shift_id' => 'Pilih salah satu shift yang aktif ketika login.',
                ]);
            }

            Session::put('transaction_shift.selected_shift_id', $shiftId);
        } elseif ($shiftId !== null) {
            throw ValidationException::withMessages(['shift_id' => 'Shift sesi ini sudah ditentukan otomatis.']);
        }

        Session::put('transaction_shift.confirmed', true);
    }

    /** @return array{pending: bool, requires_selection: bool, label: string, shifts: list<array{id: int, name: string, time: string}>} */
    public function loginPresentation(Admin $admin): array
    {
        $matches = $this->loginShifts($admin);
        $scheduled = $admin->shift_mode === self::MODE_SCHEDULE;

        if (! $scheduled) {
            $admin->loadMissing('workShift');
            $matches = new Collection($admin->workShift ? [$admin->workShift] : []);
        }

        return [
            'pending' => ! Session::get('transaction_shift.confirmed', false),
            'requires_selection' => $scheduled && $matches->count() > 1,
            'label' => $this->label($admin, $matches),
            'shifts' => array_values($matches->map(fn (AdminShift $shift): array => [
                'id' => $shift->id,
                'name' => $shift->name,
                'time' => $shift->starts_at && $shift->ends_at
                    ? OrderPresenter::clock($shift->starts_at).' - '.OrderPresenter::clock($shift->ends_at)
                    : '',
            ])->all()),
        ];
    }

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

        $matches = $this->loginShifts($admin);

        if ($matches->count() <= 1) {
            return $matches->first();
        }

        $selectedShift = $matches->firstWhere('id', Session::get('transaction_shift.selected_shift_id'));

        if (! $selectedShift instanceof AdminShift) {
            throw ValidationException::withMessages([
                'transaction_shift_id' => 'Selesaikan pemilihan shift pada popup login terlebih dahulu.',
            ]);
        }

        return $selectedShift;
    }

    /**
     * @return array{mode: string, locked_at_login: bool, label: string, caption: string, shifts: list<array{id: int, name: string, starts_at: string, ends_at: string, time: string}>}
     */
    public function presentation(Admin $admin, CarbonInterface $at): array
    {
        $shifts = $admin->shift_mode === self::MODE_SCHEDULE
            ? $this->loginShifts($admin)
            : $this->scheduledShifts();
        $matches = $admin->shift_mode === self::MODE_SCHEDULE
            ? $shifts
            : $this->matchingShifts($at, $shifts);

        return [
            'mode' => $admin->shift_mode,
            'locked_at_login' => $admin->shift_mode === self::MODE_SCHEDULE,
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
            default => $matches->firstWhere('id', Session::get('transaction_shift.selected_shift_id'))->name ?? 'Pilih shift login',
        };
    }

    /** @param Collection<int, AdminShift> $matches */
    private function caption(Admin $admin, Collection $matches): string
    {
        if ($admin->shift_mode === self::MODE_SCHEDULE && $matches->count() > 1 && Session::get('transaction_shift.selected_shift_id') === null) {
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
