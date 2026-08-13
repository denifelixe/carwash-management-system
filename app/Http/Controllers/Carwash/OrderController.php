<?php

namespace App\Http\Controllers\Carwash;

use App\Support\Carwash\Catalog;
use App\Support\Carwash\Customers;
use App\Support\Carwash\Operations;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Order and transaction management from arrival to completion (BR-05).
 */
class OrderController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'carwash/admin/Orders', [
            'orders' => Operations::orders(),
            'queue' => Operations::queue(),
            'services' => Catalog::services(),
            'serviceCategories' => Catalog::serviceCategories(),
            'customers' => Customers::all(),
            'rewards' => Catalog::rewards(),
            'crew' => Operations::crew(),
            'paymentMethods' => Operations::paymentMethods(),
        ]);
    }
}
