<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

/**
 * Runs startAutoCloseCountdown() from posReceipt.ts against a fake window
 * whose interval is ticked by hand.
 */
function runAutoCloseScript(string $body): ProcessResult
{
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/js/lib/posReceipt.ts', 'utf8');
const ast = ts.createSourceFile('posReceipt.ts', source, ts.ScriptTarget.Latest, true);
const code = ts.transpile(ast.statements
    .filter(node => (ts.isFunctionDeclaration(node) && node.name?.text === 'startAutoCloseCountdown')
        || (ts.isVariableStatement(node) && node.getText(ast).includes('AUTO_CLOSE_SECONDS =')))
    .map(node => node.getText(ast).replace(/^export /, ''))
    .join('\n'));
const element = () => {
    const listeners = {};
    return { hidden: true, textContent: '', style: {}, listeners, addEventListener: (type, listener) => { listeners[type] = listener; } };
};
const makeWindow = (opener) => {
    const nodes = {
        '[data-receipt-autoclose]': element(),
        '[data-receipt-autoclose-seconds]': element(),
        '[data-receipt-autoclose-bar]': element(),
        '[data-receipt-autoclose-cancel]': element(),
    };
    const win = {
        opener, closed: false, tick: null,
        document: { querySelector: selector => nodes[selector] ?? null },
        setInterval: callback => { win.tick = callback; return 1; },
        clearInterval: () => { win.tick = null; },
        close: () => { win.closed = true; },
        nodes,
    };
    return win;
};
const advance = (win, seconds) => { for (let i = 0; i < seconds && win.tick; i++) win.tick(); };
eval(code + '\n' + process.argv[1]);
JS;

    return Process::path(base_path())->run(['node', '-e', $script, $body]);
}

test('a slip the cashier opened counts down ten seconds and closes itself', function () {
    $result = runAutoCloseScript(<<<'JS'
const win = makeWindow({});
startAutoCloseCountdown(win);
assert.equal(win.nodes['[data-receipt-autoclose]'].hidden, false);
assert.equal(win.nodes['[data-receipt-autoclose-seconds]'].textContent, '10');
assert.equal(win.nodes['[data-receipt-autoclose-bar]'].style.transform, 'scaleX(1)');
advance(win, 4);
assert.equal(win.nodes['[data-receipt-autoclose-seconds]'].textContent, '6');
assert.equal(win.nodes['[data-receipt-autoclose-bar]'].style.transform, 'scaleX(0.6)');
assert.equal(win.closed, false);
advance(win, 6);
assert.equal(win.closed, true);
JS);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('printing pauses the countdown and it starts over afterwards', function () {
    $result = runAutoCloseScript(<<<'JS'
const win = makeWindow({});
const countdown = startAutoCloseCountdown(win);
advance(win, 8);
countdown.pause();
advance(win, 30);
assert.equal(win.closed, false);
countdown.restart();
assert.equal(win.nodes['[data-receipt-autoclose-seconds]'].textContent, '10');
advance(win, 10);
assert.equal(win.closed, true);
JS);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('keeping the slip open stops the countdown for good', function () {
    $result = runAutoCloseScript(<<<'JS'
const win = makeWindow({});
const countdown = startAutoCloseCountdown(win);
win.nodes['[data-receipt-autoclose-cancel]'].listeners.click();
assert.equal(win.nodes['[data-receipt-autoclose]'].hidden, true);
countdown.restart();
advance(win, 30);
assert.equal(win.closed, false);
JS);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('a slip opened from the link or QR never closes itself', function () {
    $result = runAutoCloseScript(<<<'JS'
const win = makeWindow(null);
startAutoCloseCountdown(win);
assert.equal(win.nodes['[data-receipt-autoclose]'].hidden, true);
assert.equal(win.tick, null);
JS);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('the countdown is wired to print, download and copy and never reaches paper', function () {
    expect(file_get_contents(resource_path('js/lib/posReceipt.ts')))
        ->toContain("receiptWindow.addEventListener('beforeprint', countdown.pause);")
        ->toContain("receiptWindow.addEventListener('afterprint', countdown.restart);")
        ->toContain('data-receipt-autoclose hidden')
        ->toContain('.autoclose { display: none !important; }');
});
