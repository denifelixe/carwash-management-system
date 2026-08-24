<?php

namespace App\Http\Controllers\Demo;

use App\Support\Demo\Catalog;
use App\Support\Demo\Customers;
use App\Support\Demo\DateFilter;
use App\Support\Demo\Operations;
use App\Support\Demo\Reports;
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

        return $this->page($request, 'demo/admin/Orders', [
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
