<?php

namespace App\Support\Admin;

use App\Models\Member;
use App\Models\Order;
use App\Support\Demo\DateFilter;
use App\Support\VehiclePlate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

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
    public static function stats(int $stampTarget): array
    {
        $stampTotals = Order::query()
            ->whereNotNull('member_id')
            ->where('status', '!=', 'batal')
            ->groupBy('member_id')
            ->selectRaw('member_id, SUM(stamps_earned) as stamps')
            ->pluck('stamps');

        return [
            'total' => Member::query()->count(),
            'active' => Member::query()->where('is_active', true)->count(),
            'withAccount' => Member::query()->whereNotNull('password')->count(),
            'circulatingStamps' => $stampTotals->sum(
                fn (mixed $stamps): int => $stampTarget > 0 ? (int) $stamps % $stampTarget : (int) $stamps,
            ),
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
            ->with(['serviceVariations:id,service_id', 'transactions.recordedBy:id,name', 'crew:id,name'])
            ->latest('service_date')
            ->latest('id')
            ->limit(50)
            ->get();

        return [
            'customer' => OrderPresenter::customer($member),
            'orders' => $orders->map(fn (Order $order): array => OrderPresenter::order($order))->all(),
            'stampHistory' => self::stampHistory($orders),
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array<string, mixed>>
     */
    private static function stampHistory(Collection $orders): array
    {
        return $orders
            ->where('status', '!=', 'batal')
            ->where('stamps_earned', '>', 0)
            ->values()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'title' => $order->serviceVariations->pluck('pivot.service_name')->join(', '),
                'detail' => $order->vehicle_plate,
                'stamps' => (int) $order->stamps_earned,
                'type' => 'earn',
                'date' => DateFilter::format($order->service_date->toDateString()),
                'icon' => Str::contains($order->vehicle_name, ['Motor', 'NMax', 'Vario']) ? '🛵' : '✨',
            ])
            ->all();
    }
}
