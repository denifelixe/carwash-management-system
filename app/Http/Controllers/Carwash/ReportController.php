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
        return $this->page($request, 'carwash/admin/Reports', [
            'stats' => Reports::todayStats(),
            'revenueTrend' => Reports::revenueTrend(),
            'monthlyTrend' => Reports::monthlyTrend(),
            'topServices' => Reports::topServices(),
            'customerActivity' => Reports::customerActivity(),
            'bookingSummary' => Reports::bookingSummary(),
            'inventorySummary' => Reports::inventorySummary(),
            'cashSummary' => Finance::summary(),
            'shifts' => Brand::shifts(),
        ]);
    }
}
