<?php

namespace App\Http\Controllers\Carwash;

use App\Http\Controllers\Controller;
use App\Support\Carwash\Brand;
use App\Support\Carwash\Catalog;
use App\Support\Carwash\Customers;
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
        return Inertia::render('carwash/auth/MemberLogin', [
            'brand' => Brand::identity(),
        ]);
    }

    public function register(): Response
    {
        return Inertia::render('carwash/auth/MemberRegister', [
            'brand' => Brand::identity(),
        ]);
    }

    public function dashboard(): Response
    {
        return $this->page('carwash/member/Dashboard', [
            'stampHistory' => Customers::stampHistory(),
            'washHistory' => Customers::washHistory(),
            'rewards' => Catalog::rewards(),
            'promos' => Brand::promos(),
        ]);
    }

    public function stamps(): Response
    {
        return $this->page('carwash/member/Stamps', [
            'stampHistory' => Customers::stampHistory(),
            'washHistory' => Customers::washHistory(),
            'rewards' => Catalog::rewards(),
        ]);
    }

    public function services(): Response
    {
        return $this->page('carwash/member/Services', [
            'services' => Catalog::services(),
            'categories' => Catalog::serviceCategories(),
        ]);
    }

    public function rewards(): Response
    {
        return $this->page('carwash/member/Rewards', [
            'rewards' => Catalog::rewards(),
            'categories' => Catalog::rewardCategories(),
            'vouchers' => Customers::vouchers(),
        ]);
    }

    public function profile(): Response
    {
        return $this->page('carwash/member/Profile', [
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
