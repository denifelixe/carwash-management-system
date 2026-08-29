<?php

namespace App\Http\Controllers\Demo;

use App\Support\Demo\Brand;
use App\Support\Demo\DateFilter;
use App\Support\Demo\Finance;
use App\Support\Demo\Operations;
use App\Support\Demo\Reports;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Money in and money out for daily operations (BR-10).
 */
class FinanceController extends AdminController
{
    public function index(Request $request): Response
    {
        // No date in the URL means the day the module is being used on.
        $date = DateFilter::fromRequest($request) ?: Reports::todayDate();

        return $this->page($request, 'admin/Finance', [
            'moneyIn' => DateFilter::apply(Finance::moneyIn(), $date),
            'moneyOut' => DateFilter::apply(Finance::moneyOut(), $date),
            'filters' => DateFilter::meta($date),
            'incomeCategories' => Finance::incomeCategories(),
            'expenseCategories' => Finance::expenseCategories(),
            'cashSummary' => Finance::summary($date),
            'paymentMethods' => Operations::paymentMethods(),
            'shifts' => Brand::shifts(),
            'orders' => Operations::orders(),
            'capabilities' => [
                'create' => true,
                'update' => true,
                'delete' => true,
            ],
        ]);
    }
}
