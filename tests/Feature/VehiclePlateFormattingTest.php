<?php

use Illuminate\Support\Facades\Process;

test('plate modes preserve special input and keep ordinary input segmented', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const assert = require('node:assert/strict');
const ts = require('typescript');
const { parse, compileScript } = require('@vue/compiler-sfc');
const vue = require('vue');
function load(source, resolve = require) {
    const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText;
    const module = { exports: {} };
    new Function('require', 'module', 'exports', code)(resolve, module, module.exports);
    return module.exports;
}
const helpers = load(fs.readFileSync('resources/js/lib/vehiclePlate.ts', 'utf8'));
assert.equal(helpers.isSpecialPlate(''), false);
assert.equal(helpers.isSpecialPlate('b 1234 cde'), false);
assert.equal(helpers.isSpecialPlate('84348-00'), true);
assert.equal(helpers.formatPlate('84348-00'), '84348-00');
const { descriptor } = parse(fs.readFileSync('resources/js/components/admin/PlateInput.vue', 'utf8'));
const compiled = compileScript(descriptor, { id: 'plate-test' });
const component = load(compiled.content, name => name === '@/lib/vehiclePlate' ? helpers : require(name)).default;
component.render = () => null;
const renderer = vue.createRenderer({
    createComment: () => ({}), insert() {}, remove() {}, parentNode() {}, nextSibling() {},
    createElement: () => ({}), createText: () => ({}), setText() {}, setElementText() {}, patchProp() {},
});
async function check() {
    const value = vue.ref('');
    const special = vue.ref(false);
    const app = renderer.createApp({
        render: () => vue.h(component, {
            id: 'plate', modelValue: value.value, special: special.value,
            'onUpdate:modelValue': next => value.value = next,
            'onUpdate:special': next => special.value = next,
        }),
    });
    const root = app.mount({});
    const state = root.$.subTree.component.setupState;
    assert.equal(state.special, false);
    state.special = true;
    await vue.nextTick();
    state.plate = '84348-00';
    await vue.nextTick();
    assert.equal(value.value, '84348-00');
    assert.equal(special.value, true);
    value.value = 'B1234CD';
    special.value = false;
    await vue.nextTick();
    assert.equal(state.segments.prefix, 'B');
    assert.equal(state.segments.digits, '1234');
    assert.equal(state.segments.suffix, 'CD');
    value.value = '84348-00';
    special.value = helpers.isSpecialPlate(value.value);
    await vue.nextTick();
    assert.equal(state.plate, '84348-00');
    assert.equal(state.special, true);
    app.unmount();
}
check().catch(error => { console.error(error); process.exitCode = 1; });
JS;

    $result = Process::path(base_path())->timeout(30)->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

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
    'orders' => ['js/pages/admin/Orders.vue', 9],
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

test('the plate column splitter keeps a half typed plate in order', function () {
    $formatter = file_get_contents(resource_path('js/lib/vehiclePlate.ts'));

    expect($formatter)
        ->toContain('export function splitPlate(')
        ->toContain('prefix: take(/^[A-Z]{1,2}/)')
        ->toContain('digits: take(/^\d{1,4}/)')
        ->toContain('suffix: take(/^[A-Z]{1,3}/)')
        ->toContain('prefix: 2,');
});

test('the plate field types across three columns without a tab press', function () {
    $input = file_get_contents(
        resource_path('js/components/admin/PlateInput.vue'),
    );

    expect($input)
        // A column keeps only what it accepts and hands the rest forward.
        ->toContain('.filter((character) => allowed[key].test(character))')
        ->toContain('.filter((character) => !allowed[key].test(character))')
        ->toContain("focusSegment(next, 'end')")
        // A full column jumps ahead on its own.
        ->toContain('if (kept.length === plateSegmentLengths[key])')
        // Backspace at the head of a column eats into the one before it.
        ->toContain('@keydown.backspace="handleBackspace(column.key, $event)"')
        ->toContain('segments[previous] = segments[previous].slice(0, -1)')
        ->toContain("focusSegment(previous, 'end')")
        // Every column is upper-cased as it is typed.
        ->toContain('normalizePlate(field.value)')
        ->toContain('uppercase')
        ->toContain('autocapitalize="characters"')
        // The model stays the canonical, space-free plate.
        ->toContain(
            '`${segments.prefix}${segments.digits}${segments.suffix}`',
        );
});

test('every plate entry field uses the shared column input', function (
    string $path,
    string $binding,
) {
    $view = file_get_contents(resource_path($path));

    expect($view)
        ->toContain("import PlateInput from '@/components/admin/PlateInput.vue';")
        ->toContain('<PlateInput')
        ->toContain($binding)
        ->not->toContain('placeholder="Plat nomor"');
})->with([
    'order entry' => ['js/pages/admin/Orders.vue', 'v-model="draft.plate"'],
    'cashier member entry' => [
        'js/pages/admin/Pos.vue',
        'v-model="memberDraft.plate"',
    ],
    'booking entry' => ['js/pages/admin/Bookings.vue', 'v-model="draft.plate"'],
    'member vehicles' => [
        'js/pages/admin/Customers.vue',
        'v-model="vehicle.plate"',
    ],
    'lead entry' => ['js/pages/admin/Leads.vue', 'v-model="draft.plate"'],
    'member registration' => [
        'js/pages/demo/auth/MemberRegister.vue',
        'v-model="form.plate"',
    ],
]);
