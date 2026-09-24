<?php

namespace App\Support\Admin;

use App\Models\Member;
use App\Models\Order;
use App\Support\VehiclePlate;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MemberQueries
{
    public const PER_PAGE = 15;

    /** @var list<string> */
    public const STATUS_FILTERS = ['Semua', 'aktif', 'tidak aktif'];

    /** @var list<string> */
    public const ACCOUNT_FILTERS = ['Punya akun portal', 'Tidak punya akun portal'];

    /** @var list<string> */
    public const VEHICLE_TYPES = ['Mobil', 'Motor'];

    /**
     * @return array{q: string, status: string, account: string, page: int}
     */
    public static function filters(Request $request): array
    {
        $status = $request->string('status')->toString();
        $account = $request->string('account')->toString();

        return [
            'q' => $request->string('q')->squish()->toString(),
            'status' => in_array($status, self::STATUS_FILTERS, true) ? $status : 'Semua',
            'account' => in_array($account, self::ACCOUNT_FILTERS, true) ? $account : 'Semua',
            'page' => max(1, $request->integer('page', 1)),
        ];
    }

    /**
     * @param  array{q: string, status: string, account: string, page: int}  $filters
     * @return LengthAwarePaginator<int, Member>
     */
    public static function page(array $filters): LengthAwarePaginator
    {
        $plate = VehiclePlate::normalize($filters['q']);

        return OrderQueries::withMemberAggregates(Member::query())
            ->when($filters['status'] !== 'Semua', fn ($query) => $query->where('is_active', $filters['status'] === 'aktif'))
            ->when(
                $filters['account'] !== 'Semua',
                fn ($query) => $filters['account'] === 'Punya akun portal'
                    ? $query->whereNotNull('password')
                    : $query->whereNull('password'),
            )
            ->when($filters['q'] !== '', function ($query) use ($filters, $plate): void {
                $like = '%'.$filters['q'].'%';

                $query->where(function ($searchQuery) use ($like, $plate): void {
                    $searchQuery
                        ->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhereHas('vehicles', fn ($vehicleQuery) => $vehicleQuery->where('plate', 'like', '%'.$plate.'%'));
                });
            })
            ->orderBy('name')
            ->paginate(self::PER_PAGE, page: $filters['page']);
    }

    /**
     * @return array{total: int, active: int, withAccount: int, circulatingStamps: int}
     */
    public static function stats(): array
    {
        return [
            'total' => Member::query()->count(),
            'active' => Member::query()->where('is_active', true)->count(),
            'withAccount' => Member::query()->whereNotNull('password')->count(),
            'circulatingStamps' => MemberStamps::circulating(),
        ];
    }

    /**
     * @return array{customer: array<string, mixed>, orders: list<array<string, mixed>>, stampHistory: list<array<string, mixed>>}|null
     */
    public static function detail(?int $memberId): ?array
    {
        if ($memberId === null) {
            return null;
        }

        $member = OrderQueries::withMemberAggregates(Member::query())->find($memberId);

        if (! $member instanceof Member) {
            return null;
        }

        $orders = Order::query()
            ->whereBelongsTo($member)
            ->with([
                'serviceVariations:id,service_id',
                'transactions.recordedBy:id,name',
                'createdBy:id,name',
                'handledByAdmin:id,name',
                'crew:id,name',
            ])
            ->latest('service_date')
            ->latest('id')
            ->limit(50)
            ->get();

        return [
            'customer' => OrderPresenter::customer($member),
            'orders' => $orders->map(fn (Order $order): array => OrderPresenter::order($order))->all(),
            'stampHistory' => MemberStamps::history($member),
        ];
    }
}
