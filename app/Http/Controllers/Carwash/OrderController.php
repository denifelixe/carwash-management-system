<?php

namespace App\Http\Controllers\Carwash;

use App\Support\Carwash\Catalog;
use App\Support\Carwash\Customers;
use App\Support\Carwash\DateFilter;
use App\Support\Carwash\Operations;
use App\Support\Carwash\Reports;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Order handling from arrival to completion (BR-05). Payment itself belongs to
 * the cashier module, so this page only tracks the order lifecycle.
 */
class OrderController extends AdminController
{
    public function index(Request $request): Response
    {
        // No date in the URL means the day the module is being used on.
        $date = DateFilter::fromRequest($request) ?: Reports::todayDate();

        return $this->page($request, 'carwash/admin/Orders', [
            'orders' => DateFilter::apply(Operations::orders(), $date),
            'filters' => DateFilter::meta($date),
            'orderStatuses' => Operations::orderStatuses(),
            'editableOrderStatuses' => Operations::editableOrderStatuses(),
            'upcoming' => Operations::bookings(),
            'services' => Catalog::services(),
            'serviceCategories' => Catalog::serviceCategories(),
            'customers' => Customers::all(),
            'crew' => Operations::crew(),
            'paymentMethods' => Operations::paymentMethods(),
        ]);
    }
}
