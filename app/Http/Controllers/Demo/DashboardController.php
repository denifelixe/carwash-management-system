<?php

namespace App\Http\Controllers\Demo;

use App\Support\Demo\DateFilter;
use App\Support\Demo\Finance;
use App\Support\Demo\Operations;
use App\Support\Demo\Reports;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Operational dashboard for Owner and Manager (BR-12).
 */
class DashboardController extends AdminController
{
    public function index(Request $request): Response
    {
        // No date in the URL means the day the module is being used on.
        $date = DateFilter::fromRequest($request) ?: Reports::todayDate();

        return $this->page($request, 'admin/Dashboard', [
            'filterUrl' => route('demo.admin.dashboard', absolute: false),
            'stats' => Reports::dashboardStats($date),
            'filters' => DateFilter::meta($date),
            'shifts' => Finance::shiftSummary($date),
            'orderSummary' => Operations::orderSummary($date),
            'cashSummary' => Finance::summary($date),
        ]);
    }
}
