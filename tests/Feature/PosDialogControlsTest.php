<?php

use Illuminate\Support\Facades\Process;

test('the payment submit button lives in the pinned dialog footer', function () {
    $posPage = file_get_contents(resource_path('js/pages/admin/Pos.vue'));
    $footerStart = mb_strpos($posPage, '<template v-if="selectedOrder" #footer>');

    expect($footerStart)->not->toBeFalse();

    $footer = mb_substr($posPage, $footerStart, mb_strpos($posPage, '</ModalDialog>', $footerStart) - $footerStart);

    expect($footer)
        ->toContain('@click="submitPayment"')
        ->toContain("'Memproses…'")
        ->toContain('paymentForm.errors')
        ->and(substr_count($posPage, '@click="submitPayment"'))->toBe(1);
});

test('dialogs are closable by default and untitled ones get a floating close button', function () {
    expect(file_get_contents(resource_path('js/components/demo/ModalDialog.vue')))
        ->toContain('{ dismissible: true }')
        ->toContain('@pointerdown.self="pressedBackdrop = true"')
        ->toContain('@click.self="closeFromBackdrop"')
        ->toContain('v-if="!title && dismissible"')
        ->toContain('aria-label="Tutup"');
});

test('a backdrop click closes a dismissible dialog only when it started on the backdrop', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/js/components/demo/ModalDialog.vue', 'utf8');
const setupScript = source.split('<script setup lang="ts">')[1].split('</script>')[0];
const ast = ts.createSourceFile('ModalDialog.ts', setupScript, ts.ScriptTarget.Latest, true);
const code = ts.transpile(ast.statements
    .filter(node => (ts.isFunctionDeclaration(node) && ['requestClose', 'closeFromBackdrop'].includes(node.name?.text))
        || (ts.isVariableStatement(node) && node.getText(ast).includes('pressedBackdrop')))
    .map(node => node.getText(ast))
    .join('\n'));
const ref = value => ({ value });
let closed = 0;
const emit = () => { closed += 1; };
const props = { dismissible: true };
eval(code + `
closeFromBackdrop();
assert.equal(closed, 0, 'a drag released over the backdrop must not close');
pressedBackdrop.value = true;
closeFromBackdrop();
assert.equal(closed, 1);
assert.equal(pressedBackdrop.value, false);
props.dismissible = false;
pressedBackdrop.value = true;
closeFromBackdrop();
assert.equal(closed, 1, 'a locked dialog stays open');
`);
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('escape closes only the topmost dismissible dialog', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/js/components/demo/ModalDialog.vue', 'utf8');
const moduleScript = source.split('<script lang="ts">')[1].split('</script>')[0];
const setupScript = source.split('<script setup lang="ts">')[1].split('</script>')[0];
const ast = ts.createSourceFile('ModalDialog.ts', setupScript, ts.ScriptTarget.Latest, true);
const functions = ast.statements
    .filter(node => ts.isFunctionDeclaration(node) && ['requestClose', 'syncPageScrollLock'].includes(node.name?.text))
    .map(node => node.getText(ast))
    .join('\n');
const listeners = new Set();
globalThis.window = {
    addEventListener: (type, listener) => listeners.add(listener),
    removeEventListener: (type, listener) => listeners.delete(listener),
};
globalThis.document = { body: { style: {} } };
const closed = [];
const dialog = (name, dismissible) => eval(`(() => {
    const props = { dismissible: ${JSON.stringify(dismissible)} };
    const emit = () => closed.push('${name}');
    let ownsPageScrollLock = false;
    ${ts.transpile(functions)}
    return syncPageScrollLock;
})()`);
eval(ts.transpile(moduleScript) + `
const press = key => [...listeners].forEach(listener => listener({ key }));
const base = dialog('base', true);
const locked = dialog('locked', false);
base(true);
locked(true);
press('Escape');
assert.deepEqual(closed, []);
locked(false);
press('Enter');
press('Escape');
assert.deepEqual(closed, ['base']);
assert.equal(document.body.style.overflow, 'hidden');
base(false);
assert.equal(listeners.size, 0);
assert.equal(document.body.style.overflow, '');
`);
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});
