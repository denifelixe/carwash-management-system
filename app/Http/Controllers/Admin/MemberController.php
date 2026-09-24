<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SaveMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMemberRequest;
use App\Http\Requests\Admin\UpdateMemberRequest;
use App\Http\Requests\Admin\UpdateMemberStatusRequest;
use App\Models\Admin;
use App\Models\Member;
use App\Models\Reward;
use App\Support\Admin\AdminShell;
use App\Support\Admin\MemberQueries;
use App\Support\Admin\OrderPresenter;
use App\Support\Admin\Paginated;
use App\Support\Admin\RewardPresenter;
use App\Support\Admin\RewardQueries;
use App\Support\Auth\AccountSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.members.read');

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $filters = MemberQueries::filters($request);

        return Inertia::render('admin/Customers', [
            ...$adminShell->props($admin, 'Member', 'members'),
            'members' => fn (): array => Paginated::fromPaginator(
                MemberQueries::page($filters),
                fn (Member $member): array => OrderPresenter::customer($member),
            ),
            'stats' => fn (): array => MemberQueries::stats(),
            'memberDetail' => fn (): ?array => MemberQueries::detail($request->integer('member') ?: null),
            'filters' => $filters,
            'statusFilters' => MemberQueries::STATUS_FILTERS,
            'accountFilters' => MemberQueries::ACCOUNT_FILTERS,
            'vehicleTypes' => MemberQueries::VEHICLE_TYPES,
            'rewards' => fn (): array => RewardQueries::activeCatalog()
                ->map(fn (Reward $reward): array => RewardPresenter::reward($reward))
                ->all(),
            'capabilities' => [
                'create' => Gate::allows('admin.members.create'),
                'update' => Gate::allows('admin.members.update'),
            ],
        ]);
    }

    public function store(StoreMemberRequest $request, SaveMember $saveMember): RedirectResponse
    {
        $member = $saveMember->create($request->member());

        return to_route('admin.members.index', ['member' => $member->id])
            ->with('success', 'Member berhasil didaftarkan.');
    }

    public function update(UpdateMemberRequest $request, Member $member, SaveMember $saveMember): RedirectResponse
    {
        $saveMember->update($member, $request->member());

        /* A reset password signs the member out everywhere they were logged in. */
        if ($member->wasChanged('password')) {
            AccountSessions::revoke($member);
        }

        return back()->with('success', 'Data member berhasil diperbarui.');
    }

    public function updateStatus(UpdateMemberStatusRequest $request, Member $member): RedirectResponse
    {
        $member->update(['is_active' => $request->boolean('is_active')]);

        if ($member->wasChanged('is_active') && ! $member->is_active) {
            AccountSessions::revoke($member);
        }

        return back()->with('success', 'Status member berhasil diperbarui.');
    }
}
