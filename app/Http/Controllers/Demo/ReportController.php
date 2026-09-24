<?php

namespace App\Http\Controllers\Demo;

use App\Support\Admin\DailySalesCsv;
use App\Support\Admin\FinanceLogCsv;
use App\Support\Admin\FinanceReportQueries;
use App\Support\Admin\ItemSalesCsv;
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
            'financeSummary' => fn (): array => Reports::financeSummary($from, $to),
            'dailySales' => fn (): array => Reports::dailySales($from, $to),
            'itemSales' => fn (): array => Reports::itemSales($scale),
            'financeLog' => Inertia::optional(fn (): array => Reports::financeLog(
                $from,
                $to,
                $request->string('direction')->toString(),
                $request->integer('financePage'),
            )),
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

    public function exportDailySales(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = Reports::resolveRange(
            $request->query('from'),
            $request->query('to'),
        );

        return DailySalesCsv::download(
            Reports::dailySales($from, $to),
            DailySalesCsv::fileName($from, $to),
        );
    }

    public function exportItemSales(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = Reports::resolveRange(
            $request->query('from'),
            $request->query('to'),
        );

        return ItemSalesCsv::download(
            Reports::itemSales(Reports::rangeScale($from, $to)),
            ItemSalesCsv::fileName($from, $to),
        );
    }

    public function exportFinance(Request $request): StreamedResponse
    {
        ['from' => $from, 'to' => $to] = Reports::resolveRange(
            $request->query('from'),
            $request->query('to'),
        );
        $direction = FinanceReportQueries::direction($request->string('direction')->toString());

        return FinanceLogCsv::download(
            Reports::financeLogRows($from, $to, $direction),
            FinanceLogCsv::fileName($from, $to, $direction),
        );
    }
}
