<?php

namespace App\Http\Controllers\Carwash;

use App\Support\Carwash\Catalog;
use App\Support\Carwash\Customers;
use App\Support\Carwash\Operations;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Point of sale (BR-06).
 */
class PosController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'carwash/admin/Pos', [
            'services' => Catalog::services(),
            'serviceCategories' => Catalog::serviceCategories(),
            'customers' => Customers::all(),
            'transactions' => Operations::orders(),
            'paymentMethods' => Operations::paymentMethods(),
            'rewards' => Catalog::rewards(),
        ]);
    }
}
