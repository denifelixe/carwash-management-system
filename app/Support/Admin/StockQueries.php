<?php

namespace App\Support\Admin;

use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Reads for the stock module: what the outlet keeps on hand and how it moved.
 */
class StockQueries
{
    public const PER_PAGE = 15;

    /** The movement log is a second list on the same page, so it pages apart. */
    public const MOVEMENTS_PER_PAGE = 10;

    /** How many days back the report card counts movements over. */
    public const SUMMARY_DAYS = 7;

    /** @var list<string> */
    public const STATUS_FILTERS = ['Semua', 'aktif', 'tidak aktif'];

    /** @var list<string> */
    public const STOCK_FILTERS = ['Semua', 'Stok menipis'];

    /** @var list<string> */
    public const MOVEMENT_TYPES = ['masuk', 'keluar', 'penyesuaian'];

    /**
     * The working list is the stock still in use, so the module opens on the
     * active items rather than on everything ever recorded.
     *
     * @return array{q: string, category: string, status: string, stock: string, page: int, movementPage: int}
     */
    public static function filters(Request $request): array
    {
        $status = $request->string('status')->toString();
        $stock = $request->string('stock')->toString();

        return [
            'q' => $request->string('q')->squish()->toString(),
            'category' => $request->string('category')->squish()->toString() ?: 'Semua',
            'status' => in_array($status, self::STATUS_FILTERS, true) ? $status : 'aktif',
            'stock' => in_array($stock, self::STOCK_FILTERS, true) ? $stock : 'Semua',
            'page' => max(1, $request->integer('page', 1)),
            'movementPage' => max(1, $request->integer('movementPage', 1)),
        ];
    }

    /**
     * @param  array{q: string, category: string, status: string, stock: string, page: int, movementPage: int}  $filters
     * @return LengthAwarePaginator<int, StockItem>
     */
    public static function page(array $filters): LengthAwarePaginator
    {
        return StockItem::query()
            ->when($filters['status'] !== 'Semua', fn ($query) => $query->where('is_active', $filters['status'] === 'aktif'))
            ->when($filters['category'] !== 'Semua', fn ($query) => $query->where('category', $filters['category']))
            ->when($filters['stock'] === 'Stok menipis', fn ($query) => $query->whereColumn('quantity', '<=', 'min_quantity'))
            ->when($filters['q'] !== '', fn ($query) => self::applySearch($query, $filters['q']))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, page: $filters['page'], pageName: 'page');
    }

    /**
     * The movement log is whole-outlet history, so it ignores the item filters
     * above — narrowing it with them would hide the entry a cashier just made.
     *
     * @param  array{q: string, category: string, status: string, stock: string, page: int, movementPage: int}  $filters
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public static function movementPage(array $filters): LengthAwarePaginator
    {
        return StockMovement::query()
            ->with(['stockItem:id,name,sku,unit', 'recordedBy:id,name'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate(self::MOVEMENTS_PER_PAGE, page: $filters['movementPage'], pageName: 'movementPage');
    }

    /**
     * The module's own header only counts items; the money figure lives on the
     * report card, which is where an owner reads value rather than stock level.
     *
     * @return array{totalItems: int, lowStock: int}
     */
    public static function stats(): array
    {
        return [
            'totalItems' => StockItem::query()->active()->count(),
            'lowStock' => self::lowStockCount(),
        ];
    }

    /**
     * Active items only: a deactivated one cannot receive stock, so offering it
     * in the dialog would only invite a movement nobody can act on.
     *
     * @return list<array<string, mixed>>
     */
    public static function itemOptions(): array
    {
        $items = StockItem::query()
            ->active()
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return array_values($items->map(fn (StockItem $item): array => StockPresenter::itemOption($item))->all());
    }

    /**
     * Suggestions for the free-text category field, which is why they come from
     * the rows themselves rather than from a master table.
     *
     * @return list<string>
     */
    public static function categoryOptions(): array
    {
        return self::distinctColumn('category');
    }

    /** @return list<string> */
    public static function supplierOptions(): array
    {
        return self::distinctColumn('supplier');
    }

    /**
     * The report card. Deliberately range-independent: on-hand is a figure for
     * right now and the movement count is a rolling week, which is why the
     * Reports page leaves this prop out of its range reload.
     *
     * @return array{totalItems: int, lowStock: int, stockValue: int, movementsThisWeek: int, topConsumed: string}
     */
    public static function summary(): array
    {
        $since = now()->startOfDay()->subDays(self::SUMMARY_DAYS - 1);

        $topConsumed = StockMovement::query()
            ->where('type', 'keluar')
            ->where('recorded_at', '>=', $since)
            ->groupBy('stock_item_id')
            ->orderByRaw('SUM(ABS(quantity)) DESC')
            ->value('stock_item_id');

        return [
            'totalItems' => StockItem::query()->active()->count(),
            'lowStock' => self::lowStockCount(),
            'stockValue' => self::stockValue(),
            'movementsThisWeek' => StockMovement::query()->where('recorded_at', '>=', $since)->count(),
            'topConsumed' => $topConsumed !== null
                ? (string) StockItem::query()->whereKey($topConsumed)->value('name')
                : '—',
        ];
    }

    private static function lowStockCount(): int
    {
        return StockItem::query()
            ->active()
            ->whereColumn('quantity', '<=', 'min_quantity')
            ->count();
    }

    private static function stockValue(): int
    {
        return (int) StockItem::query()
            ->active()
            ->where('quantity', '>', 0)
            ->sum(DB::raw('quantity * unit_cost'));
    }

    /**
     * @return list<string>
     */
    private static function distinctColumn(string $column): array
    {
        return array_values(
            StockItem::query()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderBy($column)
                ->pluck($column)
                ->all(),
        );
    }

    /**
     * @param  Builder<StockItem>  $query
     * @return Builder<StockItem>
     */
    private static function applySearch(Builder $query, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $query->where(function ($searchQuery) use ($like): void {
            $searchQuery
                ->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('supplier', 'like', $like);
        });
    }
}
