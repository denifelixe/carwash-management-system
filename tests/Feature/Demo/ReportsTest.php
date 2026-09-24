<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Demo\Reports;
use App\Support\Demo\RoleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia;

/**
 * The report range lives in the query string, so the page has to render for any
 * range a user can type into the URL and stay internally consistent (BR-12).
 */
function openReports(array $query = []): AssertableInertia
{
    $page = null;

    test()->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->get(route('demo.admin.reports', $query))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $inertia) use (&$page) {
            $page = $inertia;
        });

    return $page;
}

/**
 * Every figure is anchored to today, so the suite stands on a fixed day and
 * keeps naming dates outright.
 */
beforeEach(function () {
    $this->travelTo('2026-08-03 09:00:00');
});

test('demo finance report and export use the same filtered ledger rows', function (): void {
    $from = CarbonImmutable::parse('2026-08-01');
    $to = CarbonImmutable::parse('2026-08-03');
    $rows = Reports::financeLogRows($from, $to);
    $props = openReports(['from' => '2026-08-01', 'to' => '2026-08-03'])->toArray()['props'];

    expect($props['financeSummary']['moneyIn'])->toBe(collect($rows)->where('direction', 'in')->sum('amount'))
        ->and($props['financeSummary']['moneyOut'])->toBe(collect($rows)->where('direction', 'out')->sum('amount'))
        ->and($props)->not->toHaveKey('financeLog');

    $this->withSession([RoleAccess::SESSION_KEY => 'owner'])->get(route('demo.admin.reports', ['from' => '2026-08-01', 'to' => '2026-08-03', 'direction' => 'out']), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'admin/Reports',
        'X-Inertia-Partial-Data' => 'financeLog',
    ])->assertOk()->assertJsonCount(6, 'props.financeLog.data')->assertJsonPath('props.financeLog.data.0.direction', 'out');

    $csv = $this->get(route('demo.admin.reports.finance.export', ['from' => '2026-08-01', 'to' => '2026-08-03', 'direction' => 'out']))
        ->assertOk()->assertDownload('laporan-keuangan-pengeluaran-2026-08-01-sd-2026-08-03.csv')->streamedContent();

    expect(array_filter(explode("\r\n", $csv)))->toHaveCount(7);
});

test('the report defaults to the last seven days', function () {
    $filters = openReports()->toArray()['props']['filters'];

    expect($filters['to'])->toBe(Reports::todayDate())
        ->and($filters['from'])->toBe('2026-07-28')
        ->and($filters['days'])->toBe(7)
        ->and($filters['granularity'])->toBe('harian');
});

test('the default range reports the same figures as the dashboard week', function () {
    $trend = openReports()->toArray()['props']['trend'];
    $week = Reports::revenueTrend();

    expect($trend)->toHaveCount(count($week));

    foreach ($trend as $index => $point) {
        expect($point['revenue'])->toBe($week[$index]['revenue'])
            ->and($point['expense'])->toBe($week[$index]['expense'])
            ->and($point['transactions'])->toBe($week[$index]['transactions']);
    }
});

test('a custom range is honoured and charted day by day', function () {
    $props = openReports(['from' => '2026-06-01', 'to' => '2026-06-30'])->toArray()['props'];

    expect($props['filters']['from'])->toBe('2026-06-01')
        ->and($props['filters']['to'])->toBe('2026-06-30')
        ->and($props['filters']['days'])->toBe(30)
        ->and($props['filters']['granularity'])->toBe('harian')
        ->and($props['trend'])->toHaveCount(30);
});

test('a long range rolls up into one bar per month', function () {
    $props = openReports(['from' => '2026-05-01', 'to' => '2026-07-31'])->toArray()['props'];

    expect($props['filters']['granularity'])->toBe('bulanan')
        ->and($props['trend'])->toHaveCount(3)
        ->and($props['trend'][0]['label'])->toBe('Mei 26')
        ->and($props['trend'][2]['label'])->toBe('Jul 26');
});

test('the demo report also caps a range at 95 days', function () {
    $filters = openReports(['from' => '2026-01-01', 'to' => '2026-07-31'])->toArray()['props']['filters'];

    expect($filters['days'])->toBe(95)
        ->and($filters['to'])->toBe('2026-07-31')
        ->and($filters['maxDays'])->toBe(95);
});

test('the range filter offers no 12-month preset and caps a picked range at 95 days', function () {
    $script = <<<'JS'
const fs = require('node:fs');
const ts = require('typescript');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/js/components/demo/DateRangeFilter.vue', 'utf8').split('<script setup lang="ts">')[1].split('</script>')[0];
const ast = ts.createSourceFile('DateRangeFilter.ts', source, ts.ScriptTarget.Latest, true);
const code = ts.transpile(ast.statements
    .filter(node => (ts.isFunctionDeclaration(node) && ['shiftDays', 'changeFrom', 'changeTo'].includes(node.name?.text))
        || (ts.isVariableStatement(node) && node.getText(ast).startsWith('const presets')))
    .map(node => node.getText(ast))
    .join('\n'), { target: ts.ScriptTarget.ES2020 });
const props = { from: '2026-09-18', to: '2026-09-24', today: '2026-09-24', maxDays: 95 };
const emitted = [];
const emit = (event, range) => emitted.push(range);
eval(code + `
assert.deepEqual(presets.map(preset => preset.days), [7, 30, 90]);
changeFrom('2026-01-01');
assert.deepEqual(emitted.at(-1), { from: '2026-01-01', to: '2026-04-05' });
Object.assign(props, emitted.at(-1));
changeTo('2026-09-24');
assert.deepEqual(emitted.at(-1), { from: '2026-06-22', to: '2026-09-24' });
Object.assign(props, emitted.at(-1));
changeFrom('2026-09-01');
assert.deepEqual(emitted.at(-1), { from: '2026-09-01', to: '2026-09-24' });
`);
JS;

    $result = Process::path(base_path())->run(['node', '-e', $script]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('every trend point carries the figures the chart and stat cards read', function () {
    $trend = openReports(['from' => '2026-05-10', 'to' => '2026-06-08'])->toArray()['props']['trend'];

    foreach ($trend as $point) {
        expect($point)->toHaveKeys(['label', 'caption', 'revenue', 'expense', 'transactions'])
            ->and($point['revenue'])->toBeGreaterThan(0)
            ->and($point['transactions'])->toBeGreaterThan(0)
            // An expense above revenue would flip the margin card negative.
            ->and($point['expense'])->toBeLessThan($point['revenue']);
    }
});

test('synthesised days are stable so a shared report URL does not drift', function () {
    $first = openReports(['from' => '2026-03-02', 'to' => '2026-03-08'])->toArray()['props']['trend'];
    $second = openReports(['from' => '2026-03-02', 'to' => '2026-03-08'])->toArray()['props']['trend'];

    expect($first)->toBe($second);
});

test('count figures grow with the range so the cards stay coherent', function () {
    $week = openReports()->toArray()['props'];
    $quarter = openReports(['from' => '2026-05-06', 'to' => '2026-08-03'])->toArray()['props'];

    expect($quarter['bookingSummary']['total'])->toBeGreaterThan($week['bookingSummary']['total'])
        ->and($quarter['customerBase']['newLeads'])->toBeGreaterThan($week['customerBase']['newLeads'])
        ->and($quarter['topServices'][0]['orders'])->toBeGreaterThan($week['topServices'][0]['orders'])
        // Rates and current-state counts describe a moment, not a span.
        ->and($quarter['bookingSummary']['showRate'])->toBe($week['bookingSummary']['showRate'])
        ->and($quarter['customerBase']['openLeads'])->toBe($week['customerBase']['openLeads'])
        ->and($quarter['customerBase']['churnRisk'])->toBe($week['customerBase']['churnRisk']);
});

dataset('unusable ranges', [
    'reversed' => [['from' => '2026-08-03', 'to' => '2026-07-28'], '2026-07-28', '2026-08-03'],
    'future end' => [['from' => '2026-08-01', 'to' => '2030-01-01'], '2026-08-01', '2026-08-03'],
    'unparsable' => [['from' => 'kemarin', 'to' => 'besok'], '2026-07-28', '2026-08-03'],
    'rolled over' => [['from' => '2026-02-31', 'to' => '2026-08-03'], '2026-07-28', '2026-08-03'],
    'only a start' => [['from' => '2026-06-01'], '2026-06-01', '2026-06-07'],
    'only an end' => [['to' => '2026-06-30'], '2026-06-24', '2026-06-30'],
]);

test('an unusable range is clamped instead of failing the page', function (array $query, string $from, string $to) {
    $filters = openReports($query)->toArray()['props']['filters'];

    expect($filters['from'])->toBe($from)
        ->and($filters['to'])->toBe($to);
})->with('unusable ranges');

test('the range cannot reach further back than the retained history', function () {
    $earliest = CarbonImmutable::parse(Reports::todayDate())->subDays(730);
    /* The end stays within the 95-day cap of the floor, so only the floor moves the start. */
    $filters = openReports(['from' => '2019-01-01', 'to' => $earliest->addDays(30)->toDateString()])->toArray()['props']['filters'];

    expect($filters['from'])->toBe($earliest->toDateString());
});

test('every top service has the revenue and order count the contribution bars need', function () {
    $services = openReports()->toArray()['props']['topServices'];

    expect($services)->toHaveCount(5);

    foreach ($services as $service) {
        expect($service['name'])->not->toBeEmpty()
            ->and($service['revenue'])->toBeGreaterThan(0)
            ->and($service['orders'])->toBeGreaterThan(0);
    }
});
