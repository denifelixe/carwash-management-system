<?php

namespace App\Http\Controllers\Carwash;

use App\Support\Carwash\Brand;
use App\Support\Carwash\Catalog;
use App\Support\Carwash\Customers;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Reward catalog and stamp requirements (BR-13).
 */
class RewardController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'carwash/admin/Rewards', [
            'rewards' => Catalog::rewards(),
            'categories' => Catalog::rewardCategories(),
            'stampTarget' => Brand::identity()['stampTarget'],
            'customers' => Customers::all(),
        ]);
    }
}
