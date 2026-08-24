<?php

namespace App\Http\Controllers\Demo;

use App\Support\Demo\Brand;
use App\Support\Demo\Finance;
use App\Support\Demo\Reports;
use Illuminate\Http\Request;
use Inertia\Response;

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

        return $this->page($request, 'demo/admin/Reports', [
            'stats' => Reports::todayStats(),
            'trend' => Reports::trend($from, $to),
            'filters' => Reports::rangeMeta($from, $to),
            'topServices' => Reports::topServices($scale),
            'customerActivity' => Reports::customerActivity($scale),
            'bookingSummary' => Reports::bookingSummary($scale),
            'inventorySummary' => Reports::inventorySummary(),
            'cashSummary' => Finance::summary(),
            'shifts' => Brand::shifts(),
        ]);
    }
}
