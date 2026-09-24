<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Support\Demo\Brand;
use App\Support\Member\MemberPortalQueries;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The live member portal (BR-01 … BR-04), sharing its pages with the demo.
 *
 * Read-only by design: the portal shows the stamp wallet, visits, and the
 * reward catalog. Booking, payment, and redemption all happen at the till.
 */
class PortalController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $member = $this->member($request);

        return $this->page($member, 'member/Dashboard', [
            'stampHistory' => MemberPortalQueries::stampHistory($member),
            'washHistory' => MemberPortalQueries::washHistory($member),
            'rewards' => MemberPortalQueries::rewards(),
            'promos' => [],
        ]);
    }

    public function stamps(Request $request): Response
    {
        $member = $this->member($request);

        return $this->page($member, 'member/Stamps', [
            'stampHistory' => MemberPortalQueries::stampHistory($member),
            'washHistory' => MemberPortalQueries::washHistory($member),
            'rewards' => MemberPortalQueries::rewards(),
        ]);
    }

    public function services(Request $request): Response
    {
        $services = MemberPortalQueries::services();

        return $this->page($this->member($request), 'member/Services', [
            'services' => $services,
            'categories' => MemberPortalQueries::categoriesOf($services),
        ]);
    }

    public function rewards(Request $request): Response
    {
        $rewards = MemberPortalQueries::rewards();

        return $this->page($this->member($request), 'member/Rewards', [
            'rewards' => $rewards,
            'categories' => MemberPortalQueries::categoriesOf($rewards),
            'vouchers' => [],
        ]);
    }

    public function profile(Request $request): Response
    {
        $member = $this->member($request);

        return $this->page($member, 'member/Profile', [
            'washHistory' => MemberPortalQueries::washHistory($member),
            'vouchers' => [],
        ]);
    }

    private function member(Request $request): Member
    {
        /** @var Member $member */
        $member = $request->user('member');

        return $member;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function page(Member $member, string $component, array $props): Response
    {
        return Inertia::render($component, [
            'mode' => 'live',
            'brand' => Brand::identity(),
            'member' => MemberPortalQueries::member($member),
            /* The live portal sends no notifications yet. */
            'notifications' => [],
            ...$props,
        ]);
    }
}
