<?php

namespace App\Http\Controllers\Demo;

use App\Support\Admin\OrderLogCsv;
use App\Support\Demo\Reports;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporting and monitoring across operations and finance (BR-12).
 */
class ReportController extends AdminController
{
    public function index(Request $request): Response
    {
        ['from' => $from, 'to' => $to] = Reports::resolveRange(
            $request->query('from'),
            $request->query('to'),
        );

        $scale = Reports::rangeScale($from, $to);

        return $this->page($request, 'admin/Reports', [
            'trend' => Reports::trend($from, $to),
            'filters' => Reports::rangeMeta($from, $to),
            'topServices' => Reports::topServices($scale),
            'customerBase' => Reports::customerBase($scale),
            'bookingSummary' => Reports::bookingSummary($scale),
            'orderLog' => Inertia::optional(fn (): array => Reports::orderLog(
                $from,
                $to,
                $request->string('service')->toString() ?: null,
                (int) $request->integer('orderPage'),
            )),
            'inventorySummary' => Reports::inventorySummary(),
            'shifts' => Reports::shiftSummary($from, $to),
            'capabilities' => [
                'read' => true,
            ],
        ]);
    }

    public function exportOrders(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = Reports::resolveRange(
            $request->query('from'),
            $request->query('to'),
        );
        $service = $request->string('service')->toString() ?: null;

        return OrderLogCsv::download(
            Reports::orderLogRows($from, $to, $service),
            OrderLogCsv::fileName($from, $to, $service),
        );
    }
}
