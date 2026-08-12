<?php

namespace App\Http\Controllers\Carwash;

use App\Support\Carwash\Brand;
use App\Support\Carwash\Customers;
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
        return $this->page($request, 'carwash/admin/Dashboard', [
            'stats' => Reports::todayStats(),
            'revenueTrend' => Reports::revenueTrend(),
            'topServices' => Reports::topServices(),
            'shifts' => Brand::shifts(),
            'queue' => Operations::queue(),
            'crew' => Operations::crew(),
            'cashSummary' => Finance::summary(),
            'customerCount' => count(Customers::all()),
        ]);
    }
}
