<?php

use App\Support\Carwash\Reports;
use App\Support\Carwash\RoleAccess;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

/**
 * The report range lives in the query string, so the page has to render for any
 * range a user can type into the URL and stay internally consistent (BR-12).
 */
function openReports(array $query = []): AssertableInertia
{
    $page = null;

    test()->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->get(route('carwash.admin.reports', $query))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $inertia) use (&$page) {
            $page = $inertia;
        });

    return $page;
}

test('the report defaults to the last seven days', function () {
    $filters = openReports()->toArray()['props']['filters'];

    expect($filters['to'])->toBe(Reports::TODAY)
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
    $props = openReports(['from' => '2026-02-01', 'to' => '2026-07-31'])->toArray()['props'];

    expect($props['filters']['granularity'])->toBe('bulanan')
        ->and($props['trend'])->toHaveCount(6)
        ->and($props['trend'][0]['label'])->toBe('Feb 26')
        ->and($props['trend'][5]['label'])->toBe('Jul 26');
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
        ->and($quarter['customerActivity']['stampsIssued'])->toBeGreaterThan($week['customerActivity']['stampsIssued'])
        ->and($quarter['topServices'][0]['orders'])->toBeGreaterThan($week['topServices'][0]['orders'])
        // Rates and current-state counts describe a moment, not a span.
        ->and($quarter['bookingSummary']['showRate'])->toBe($week['bookingSummary']['showRate'])
        ->and($quarter['customerActivity']['churnRisk'])->toBe($week['customerActivity']['churnRisk']);
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
    $filters = openReports(['from' => '2019-01-01', 'to' => '2026-08-03'])->toArray()['props']['filters'];

    expect($filters['from'])->toBe(
        CarbonImmutable::parse(Reports::TODAY)->subDays(730)->toDateString()
    );
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
