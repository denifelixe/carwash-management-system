<?php

namespace App\Http\Controllers\Carwash;

use App\Support\Carwash\Catalog;
use App\Support\Carwash\Customers;
use App\Support\Carwash\Operations;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Point of sale (BR-06).
 *
 * The cashier settles orders that already exist rather than composing new ones,
 * so the page is fed the order list and only needs the catalog to spell out each
 * order's `serviceIds`. Rewards are redeemed by the front office at order intake,
 * so they never reach this page.
 */
class PosController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'carwash/admin/Pos', [
            'orders' => Operations::orders(),
            'services' => Catalog::services(),
            'customers' => Customers::all(),
            'paymentMethods' => Operations::paymentMethods(),
        ]);
    }
}
