<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Support\Demo\Brand;
use App\Support\Demo\Catalog;
use App\Support\Demo\Customers;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer web application (BR-01 … BR-04).
 *
 * Read-only by design: the portal informs and tracks loyalty, while booking,
 * payment, and redemption stay on the admin side.
 */
class MemberController extends Controller
{
    public function login(): Response
    {
        return Inertia::render('demo/auth/MemberLogin', [
            'brand' => Brand::identity(),
        ]);
    }

    public function register(): Response
    {
        return Inertia::render('demo/auth/MemberRegister', [
            'brand' => Brand::identity(),
        ]);
    }

    public function dashboard(): Response
    {
        return $this->page('demo/member/Dashboard', [
            'stampHistory' => Customers::stampHistory(),
            'washHistory' => Customers::washHistory(),
            'rewards' => Catalog::rewards(),
            'promos' => Brand::promos(),
        ]);
    }

    public function stamps(): Response
    {
        return $this->page('demo/member/Stamps', [
            'stampHistory' => Customers::stampHistory(),
            'washHistory' => Customers::washHistory(),
            'rewards' => Catalog::rewards(),
        ]);
    }

    public function services(): Response
    {
        return $this->page('demo/member/Services', [
            'services' => Catalog::services(),
            'categories' => Catalog::serviceCategories(),
        ]);
    }

    public function rewards(): Response
    {
        return $this->page('demo/member/Rewards', [
            'rewards' => Catalog::rewards(),
            'categories' => Catalog::rewardCategories(),
            'vouchers' => Customers::vouchers(),
        ]);
    }

    public function profile(): Response
    {
        return $this->page('demo/member/Profile', [
            'washHistory' => Customers::washHistory(),
            'vouchers' => Customers::vouchers(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function page(string $component, array $props = []): Response
    {
        return Inertia::render($component, array_merge([
            'brand' => Brand::identity(),
            'member' => Customers::member(),
            'notifications' => Brand::memberNotifications(),
        ], $props));
    }
}
