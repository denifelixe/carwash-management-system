<?php

namespace App\Http\Controllers\Carwash;

use App\Support\Carwash\DateFilter;
use App\Support\Carwash\Finance;
use App\Support\Carwash\Operations;
use App\Support\Carwash\Reports;
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

        return $this->page($request, 'carwash/admin/Dashboard', [
            'stats' => Reports::dashboardStats($date),
            'filters' => DateFilter::meta($date),
            'shifts' => Finance::shiftSummary($date),
            'orderSummary' => Operations::orderSummary($date),
            'cashSummary' => Finance::summary($date),
        ]);
    }
}
