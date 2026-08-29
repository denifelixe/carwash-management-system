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
 * Point of sale (BR-06).
 *
 * The cashier settles orders that already exist rather than composing new ones,
 * so the page is fed the order list and only needs the catalog to spell out each
 * order's `serviceIds`. Reward redemption belongs to the cashier's payment flow.
 *
 * Final settlements stay date-filtered, while the complete booking schedule
 * for today and later remains visible for cashier payment handling.
 */
class PosController extends AdminController
{
    public function index(Request $request): Response
    {
        // No date in the URL means the day the module is being used on.
        $date = DateFilter::fromRequest($request) ?: Reports::todayDate();

        return $this->page($request, 'admin/Pos', [
            'orders' => DateFilter::apply(Operations::settlementOrders(), $date),
            'dailyOrders' => DateFilter::apply(Operations::orders(), $date),
            'partialPaymentBookings' => Operations::partialPaymentBookingOrders(),
            'filters' => DateFilter::meta($date),
            'services' => Catalog::services(),
            'customers' => Customers::all(),
            'rewards' => Catalog::rewards(),
            'paymentMethods' => Operations::paymentMethods(),
            'capabilities' => ['create' => true],
        ]);
    }
}
