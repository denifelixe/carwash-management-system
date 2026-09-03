<?php

test('plate formatting is presentation only and keeps the canonical normalizer', function () {
    $formatter = file_get_contents(resource_path('js/lib/vehiclePlate.ts'));

    expect($formatter)
        ->toContain("value.replace(/\\s+/g, '').toUpperCase()")
        ->toContain('normalized.match(/^([A-Z]{1,2})(\\d{1,4})([A-Z]{0,3})$/)')
        ->toContain(".filter(Boolean).join(' ')");
});

test('visible vehicle plates use the shared display formatter', function (string $path, int $usageCount) {
    $view = file_get_contents(resource_path($path));

    expect(substr_count($view, 'formatPlate('))->toBe($usageCount);
})->with([
    'orders' => ['js/pages/admin/Orders.vue', 7],
    'cashier' => ['js/pages/admin/Pos.vue', 7],
    'bookings' => ['js/pages/admin/Bookings.vue', 5],
    'members' => ['js/pages/admin/Customers.vue', 2],
    'finance' => ['js/pages/admin/Finance.vue', 3],
    'member profile' => ['js/pages/demo/member/Profile.vue', 1],
    'printed receipt' => ['js/lib/posReceipt.ts', 1],
]);

test('plate form models keep their canonical values', function (string $path, string $binding) {
    $view = file_get_contents(resource_path($path));

    expect($view)->toContain($binding);
})->with([
    'order entry' => ['js/pages/admin/Orders.vue', 'v-model="draft.plate"'],
    'cashier member entry' => ['js/pages/admin/Pos.vue', 'v-model="memberDraft.plate"'],
]);
