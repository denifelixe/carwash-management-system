<?php

use App\Models\AdminShift;
use App\Support\Admin\TransactionShiftResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/** @return Collection<int, AdminShift> */
function resolverShifts(): Collection
{
    return new Collection([
        new AdminShift([
            'name' => 'Shift Pagi',
            'starts_at' => '08:00',
            'ends_at' => '15:00',
            'is_active' => true,
        ]),
        new AdminShift([
            'name' => 'Shift Sore',
            'starts_at' => '14:00',
            'ends_at' => '22:00',
            'is_active' => true,
        ]),
        new AdminShift([
            'name' => 'Shift Malam',
            'starts_at' => '22:00',
            'ends_at' => '06:00',
            'is_active' => true,
        ]),
        new AdminShift([
            'name' => 'Tanpa Jam',
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ]),
        new AdminShift([
            'name' => 'Nonaktif',
            'starts_at' => '00:00',
            'ends_at' => '23:59',
            'is_active' => false,
        ]),
    ]);
}

test('matching shifts use start inclusive and end exclusive windows', function (string $time, array $expected) {
    $matches = (new TransactionShiftResolver)
        ->matchingShifts(CarbonImmutable::parse("2026-08-31 {$time}"), resolverShifts())
        ->pluck('name')
        ->all();

    expect($matches)->toBe($expected);
})->with([
    'morning starts' => ['08:00', ['Shift Pagi']],
    'overlap' => ['14:30', ['Shift Pagi', 'Shift Sore']],
    'morning ends' => ['15:00', ['Shift Sore']],
    'overnight starts' => ['22:00', ['Shift Malam']],
    'after midnight' => ['02:30', ['Shift Malam']],
    'overnight ends' => ['06:00', []],
    'outside every shift' => ['07:00', []],
]);

test('inactive shifts and shifts without hours never match automatically', function () {
    $matches = (new TransactionShiftResolver)
        ->matchingShifts(CarbonImmutable::parse('2026-08-31 12:00'), resolverShifts())
        ->pluck('name');

    expect($matches)
        ->not->toContain('Tanpa Jam')
        ->not->toContain('Nonaktif');
});
