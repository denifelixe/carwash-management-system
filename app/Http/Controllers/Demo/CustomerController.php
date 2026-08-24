<?php

namespace App\Http\Controllers\Demo;

use App\Support\Demo\Brand;
use App\Support\Demo\Catalog;
use App\Support\Demo\Customers;
use App\Support\Demo\Operations;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Centralized customer database with stamp history (BR-07).
 */
class CustomerController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'demo/admin/Customers', [
            'customers' => Customers::all(),
            'orders' => Operations::orders(),
            'stampHistory' => Customers::stampHistory(),
            'washHistory' => Customers::washHistory(),
            'rewards' => Catalog::rewards(),
            'stampTarget' => Brand::identity()['stampTarget'],
        ]);
    }
}
