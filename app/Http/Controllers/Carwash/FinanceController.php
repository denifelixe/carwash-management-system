<?php

namespace App\Http\Controllers\Carwash;

use App\Support\Carwash\Brand;
use App\Support\Carwash\Finance;
use App\Support\Carwash\Operations;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Money in and money out for daily operations (BR-10).
 */
class FinanceController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'carwash/admin/Finance', [
            'moneyIn' => Finance::moneyIn(),
            'moneyOut' => Finance::moneyOut(),
            'incomeCategories' => Finance::incomeCategories(),
            'expenseCategories' => Finance::expenseCategories(),
            'cashSummary' => Finance::summary(),
            'paymentMethods' => Operations::paymentMethods(),
            'shifts' => Brand::shifts(),
        ]);
    }
}
