<?php

namespace App\Http\Controllers\Demo;

use App\Support\Admin\MemberQueries;
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
        $filters = MemberQueries::filters($request);
        $stampTarget = (int) Brand::identity()['stampTarget'];
        $orders = Operations::orders();

        return $this->page($request, 'admin/Customers', [
            'members' => Customers::page($filters['q'], $filters['status'], $filters['account'], $filters['page'], MemberQueries::PER_PAGE, $stampTarget),
            'stats' => Customers::moduleStats($stampTarget),
            'memberDetail' => Customers::detail($request->integer('member') ?: null, $orders, $stampTarget),
            'filters' => $filters,
            'statusFilters' => MemberQueries::STATUS_FILTERS,
            'accountFilters' => MemberQueries::ACCOUNT_FILTERS,
            'vehicleTypes' => MemberQueries::VEHICLE_TYPES,
            'rewards' => Catalog::rewards(),
            'stampTarget' => $stampTarget,
            'capabilities' => ['create' => true, 'update' => true],
        ]);
    }
}
