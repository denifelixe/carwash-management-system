<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SaveReward;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRewardRequest;
use App\Http\Requests\Admin\UpdateRewardRequest;
use App\Http\Requests\Admin\UpdateRewardStatusRequest;
use App\Models\Admin;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Support\Admin\AdminShell;
use App\Support\Admin\Paginated;
use App\Support\Admin\RewardPresenter;
use App\Support\Admin\RewardQueries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reward catalog and stamp requirements (BR-13). The cashier redeems at the
 * POS; this module keeps the catalog and shows what has been redeemed.
 */
class RewardController extends Controller
{
    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.rewards.read');

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $filters = RewardQueries::filters($request);

        return Inertia::render('admin/Rewards', [
            ...$adminShell->props($admin, 'Reward', 'rewards'),
            'rewards' => fn (): array => RewardQueries::catalog()
                ->map(fn (Reward $reward): array => RewardPresenter::reward($reward))
                ->all(),
            'redemptions' => fn (): array => Paginated::fromPaginator(
                RewardQueries::redemptionPage($filters),
                fn (RewardRedemption $redemption): array => RewardPresenter::redemption($redemption),
            ),
            'stats' => fn (): array => RewardQueries::stats(),
            'stampBalances' => fn (): array => RewardQueries::stampBalances(),
            'categories' => fn (): array => RewardQueries::categoryOptions(),
            'serviceOptions' => fn (): array => RewardQueries::serviceOptions(),
            'filters' => $filters,
            'capabilities' => [
                'create' => Gate::allows('admin.rewards.create'),
                'update' => Gate::allows('admin.rewards.update'),
                'delete' => Gate::allows('admin.rewards.delete'),
            ],
        ]);
    }

    public function store(StoreRewardRequest $request, SaveReward $saveReward): RedirectResponse
    {
        $saveReward->handle($request->reward());

        return to_route('admin.rewards.index')
            ->with('success', 'Reward berhasil ditambahkan.');
    }

    public function update(UpdateRewardRequest $request, Reward $reward, SaveReward $saveReward): RedirectResponse
    {
        $saveReward->handle($request->reward(), $reward);

        return back()->with('success', 'Reward berhasil diperbarui.');
    }

    public function updateStatus(UpdateRewardStatusRequest $request, Reward $reward): RedirectResponse
    {
        $reward->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Status reward berhasil diperbarui.');
    }

    /**
     * Only a reward nobody has redeemed can be deleted. Once it appears in the
     * redemption history it is deactivated instead, so that history keeps
     * pointing at the reward.
     */
    public function destroy(Reward $reward): RedirectResponse
    {
        Gate::authorize('admin.rewards.delete');

        if ($reward->redemptions()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'reward' => 'Reward ini sudah pernah ditukar. Nonaktifkan saja agar riwayatnya tetap utuh.',
            ]);
        }

        $reward->delete();

        return to_route('admin.rewards.index')
            ->with('success', 'Reward berhasil dihapus.');
    }
}
