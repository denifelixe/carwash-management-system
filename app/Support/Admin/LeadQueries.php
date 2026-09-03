<?php

namespace App\Support\Admin;

use App\Models\Lead;
use App\Models\Order;
use App\Support\VehiclePlate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reads for the leads module: the non-members the outlet has already served.
 */
class LeadQueries
{
    public const PER_PAGE = 15;

    /** How many rows the order form's lead picker offers per search. */
    public const SEARCH_LIMIT = 20;

    /** @var list<string> */
    public const STATUS_FILTERS = ['Semua', 'aktif', 'tidak aktif'];

    /** @var list<string> */
    public const CONVERSION_FILTERS = ['Semua', 'Belum jadi member', 'Sudah jadi member'];

    /**
     * The working list is the leads still worth following up, so the module
     * opens on the un-converted ones rather than on everything ever recorded.
     *
     * @return array{q: string, status: string, conversion: string, page: int}
     */
    public static function filters(Request $request): array
    {
        $status = $request->string('status')->toString();
        $conversion = $request->string('conversion')->toString();

        return [
            'q' => $request->string('q')->squish()->toString(),
            'status' => in_array($status, self::STATUS_FILTERS, true) ? $status : 'Semua',
            'conversion' => in_array($conversion, self::CONVERSION_FILTERS, true) ? $conversion : 'Belum jadi member',
            'page' => max(1, $request->integer('page', 1)),
        ];
    }

    /**
     * @param  array{q: string, status: string, conversion: string, page: int}  $filters
     * @return LengthAwarePaginator<int, Lead>
     */
    public static function page(array $filters): LengthAwarePaginator
    {
        return self::withLeadAggregates(Lead::query())
            ->when($filters['status'] !== 'Semua', fn ($query) => $query->where('is_active', $filters['status'] === 'aktif'))
            ->when(
                $filters['conversion'] !== 'Semua',
                fn ($query) => $filters['conversion'] === 'Sudah jadi member'
                    ? $query->whereNotNull('converted_member_id')
                    : $query->whereNull('converted_member_id'),
            )
            ->when($filters['q'] !== '', fn ($query) => self::applySearch($query, $filters['q']))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, page: $filters['page']);
    }

    /**
     * The order form's picker. It only ever offers leads that are still open,
     * because a converted one belongs on the Member tab.
     *
     * @return list<array<string, mixed>>
     */
    public static function searchOptions(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $leads = Lead::query()
            ->whereNull('converted_member_id')
            ->where('is_active', true)
            ->where(fn ($leadQuery) => self::applySearch($leadQuery, $query))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::SEARCH_LIMIT)
            ->get();

        return array_values($leads->map(fn (Lead $lead): array => OrderPresenter::leadOption($lead))->all());
    }

    /**
     * @return array{total: int, open: int, converted: int, returning: int}
     */
    public static function stats(): array
    {
        return [
            'total' => Lead::query()->count(),
            'open' => Lead::query()->whereNull('converted_member_id')->where('is_active', true)->count(),
            'converted' => Lead::query()->whereNotNull('converted_member_id')->count(),
            'returning' => Lead::query()
                ->whereNull('converted_member_id')
                ->whereHas('orders', fn ($orderQuery) => $orderQuery->where('status', '!=', 'batal'), '>=', 2)
                ->count(),
        ];
    }

    /**
     * @return array{lead: array<string, mixed>, orders: list<array<string, mixed>>}|null
     */
    public static function detail(?int $leadId): ?array
    {
        if ($leadId === null) {
            return null;
        }

        $lead = self::withLeadAggregates(Lead::query())->find($leadId);

        if (! $lead instanceof Lead) {
            return null;
        }

        $orders = Order::query()
            ->whereBelongsTo($lead)
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
            'lead' => OrderPresenter::lead($lead),
            'orders' => array_values($orders->map(fn (Order $order): array => OrderPresenter::order($order))->all()),
        ];
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public static function withLeadAggregates(Builder $query): Builder
    {
        return $query
            ->withCount(['orders' => fn ($orderQuery) => $orderQuery->where('status', '!=', 'batal')])
            ->withSum(['orders as orders_sum_total' => fn ($orderQuery) => $orderQuery->where('status', '!=', 'batal')], 'total')
            ->withMax(['orders as last_order_date' => fn ($orderQuery) => $orderQuery->where('status', '!=', 'batal')], 'service_date');
    }

    /**
     * Name and phone match as typed; the plate matches in its stored form, so
     * "b 1234 cde" finds B1234CDE.
     *
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    private static function applySearch(Builder $query, string $term): Builder
    {
        $like = '%'.$term.'%';
        $plate = VehiclePlate::normalize($term);

        return $query->where(function ($searchQuery) use ($like, $plate): void {
            $searchQuery
                ->where('name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('vehicle_name', 'like', $like)
                ->orWhere('vehicle_plate', 'like', '%'.$plate.'%');
        });
    }
}
