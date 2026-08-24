<?php

namespace App\Http\Controllers\Demo;

use App\Support\Demo\Brand;
use App\Support\Demo\Catalog;
use App\Support\Demo\Customers;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Reward catalog and stamp requirements (BR-13).
 */
class RewardController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'demo/admin/Rewards', [
            'rewards' => Catalog::rewards(),
            'categories' => Catalog::rewardCategories(),
            'stampTarget' => Brand::identity()['stampTarget'],
            'customers' => Customers::all(),
        ]);
    }
}
