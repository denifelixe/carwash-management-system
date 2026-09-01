<?php

test('money input formats rupiah thousands while keeping a numeric model', function () {
    $component = file_get_contents(resource_path('js/components/MoneyInput.vue'));

    expect($component)
        ->toContain('formatNumber(amount)')
        ->toContain("input.value.replace(/\\D/g, '')")
        ->toContain('defineModel<number>({ required: true })')
        ->toContain('inputmode="numeric"')
        ->toContain('data-money-input');
});

test('every monetary entry field uses the shared formatted input', function (string $path, int $usageCount) {
    $page = file_get_contents(resource_path($path));

    expect(substr_count($page, '<MoneyInput'))->toBe($usageCount);
})->with([
    'finance amounts' => ['js/pages/admin/Finance.vue', 3],
    'cashier payment amounts' => ['js/pages/admin/Pos.vue', 3],
    'service prices' => ['js/pages/admin/master/Services.vue', 1],
]);
