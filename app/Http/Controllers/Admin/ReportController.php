<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\Admin\AdminShell;
use App\Support\Admin\DailySalesCsv;
use App\Support\Admin\FinanceLogCsv;
use App\Support\Admin\FinanceReportQueries;
use App\Support\Admin\ItemSalesCsv;
use App\Support\Admin\OrderLogCsv;
use App\Support\Admin\ReportQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporting and monitoring across operations and finance (BR-12).
 *
 * Read-only: every figure is restated from the module that owns it, so the
 * report cannot drift from the ledger or the order board it summarises.
 */
class ReportController extends Controller
{
    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.reports.read');

        /** @var Admin $admin */
        $admin = $request->user('admin');

        ['from' => $from, 'to' => $to] = ReportQueries::resolveRange(
            $request->query('from'),
            $request->query('to'),
        );

        return Inertia::render('admin/Reports', [
            ...$adminShell->props($admin, 'Laporan', 'reports'),
            'trend' => ReportQueries::trend($from, $to),
            'filters' => ReportQueries::rangeMeta($from, $to),
            'financeSummary' => fn (): array => FinanceReportQueries::summary($from, $to),
            'dailySales' => fn (): array => ReportQueries::dailySales($from, $to),
            'itemSales' => fn (): array => ReportQueries::itemSales($from, $to),
            'financeLog' => Inertia::optional(fn (): array => FinanceReportQueries::log(
                $from,
                $to,
                $request->string('direction')->toString(),
                $request->integer('financePage'),
            )),
            'topServices' => ReportQueries::topServices($from, $to),
            'customerBase' => ReportQueries::customerBase($from, $to),
            'bookingSummary' => ReportQueries::bookingSummary($from, $to),
            /*
             * The log behind the contribution card. Only fetched when that card
             * is opened: a year-wide range holds thousands of orders, and the
             * report itself never shows them.
             */
            'orderLog' => Inertia::optional(fn (): array => ReportQueries::orderLog(
                $from,
                $to,
                $request->string('service')->toString() ?: null,
                (int) $request->integer('orderPage'),
            )),
            'inventorySummary' => ReportQueries::inventorySummary(),
            'shifts' => ReportQueries::shiftSummary($from, $to),
            'capabilities' => [
                'read' => true,
            ],
        ]);
    }

    /**
     * The order log as a spreadsheet. Covers the whole range and the service
     * the card was opened on, never just the page on screen.
     */
    public function exportOrders(Request $request): StreamedResponse
    {
        Gate::authorize('admin.reports.read');

        ['from' => $from, 'to' => $to] = ReportQueries::resolveRange(
            $request->query('from'),
            $request->query('to'),
        );
        $service = $request->string('service')->toString() ?: null;

        return OrderLogCsv::download(
            ReportQueries::orderLogRows($from, $to, $service),
            OrderLogCsv::fileName($from, $to, $service),
        );
    }

    public function exportDailySales(Request $request): StreamedResponse
    {
        Gate::authorize('admin.reports.read');

        ['from' => $from, 'to' => $to] = ReportQueries::resolveRange(
            $request->query('from'),
            $request->query('to'),
        );

        return DailySalesCsv::download(
            ReportQueries::dailySales($from, $to),
            DailySalesCsv::fileName($from, $to),
        );
    }

    public function exportItemSales(Request $request): StreamedResponse
    {
        Gate::authorize('admin.reports.read');

        ['from' => $from, 'to' => $to] = ReportQueries::resolveRange(
            $request->query('from'),
            $request->query('to'),
        );

        return ItemSalesCsv::download(
            ReportQueries::itemSales($from, $to),
            ItemSalesCsv::fileName($from, $to),
        );
    }

    public function exportFinance(Request $request): StreamedResponse
    {
        Gate::authorize('admin.reports.read');

        ['from' => $from, 'to' => $to] = ReportQueries::resolveRange(
            $request->query('from'),
            $request->query('to'),
        );
        $direction = FinanceReportQueries::direction($request->string('direction')->toString());

        return FinanceLogCsv::download(
            FinanceReportQueries::rows($from, $to, $direction),
            FinanceLogCsv::fileName($from, $to, $direction),
        );
    }
}
