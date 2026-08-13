<?php

namespace App\Http\Controllers\Carwash;

use App\Support\Carwash\Brand;
use App\Support\Carwash\Finance;
use App\Support\Carwash\Reports;
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

        return $this->page($request, 'carwash/admin/Reports', [
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
