<?php

namespace App\Http\Controllers\Carwash;

use App\Support\Carwash\Brand;
use App\Support\Carwash\DateFilter;
use App\Support\Carwash\Finance;
use App\Support\Carwash\Operations;
use App\Support\Carwash\Reports;
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

        return $this->page($request, 'carwash/admin/Finance', [
            'moneyIn' => DateFilter::apply(Finance::moneyIn(), $date),
            'moneyOut' => DateFilter::apply(Finance::moneyOut(), $date),
            'filters' => DateFilter::meta($date),
            'incomeCategories' => Finance::incomeCategories(),
            'expenseCategories' => Finance::expenseCategories(),
            'cashSummary' => Finance::summary(),
            'paymentMethods' => Operations::paymentMethods(),
            'shifts' => Brand::shifts(),
        ]);
    }
}
