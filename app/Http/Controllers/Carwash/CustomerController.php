<?php

namespace App\Http\Controllers\Carwash;

use App\Support\Carwash\Brand;
use App\Support\Carwash\Catalog;
use App\Support\Carwash\Customers;
use App\Support\Carwash\Operations;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Centralized customer database with stamp history (BR-07).
 */
class CustomerController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'carwash/admin/Customers', [
            'customers' => Customers::all(),
            'orders' => Operations::orders(),
            'stampHistory' => Customers::stampHistory(),
            'washHistory' => Customers::washHistory(),
            'rewards' => Catalog::rewards(),
            'stampTarget' => Brand::identity()['stampTarget'],
        ]);
    }
}
