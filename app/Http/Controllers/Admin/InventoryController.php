<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordStockMovement;
use App\Actions\Admin\SaveStockItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStockItemRequest;
use App\Http\Requests\Admin\StoreStockMovementRequest;
use App\Http\Requests\Admin\UpdateStockItemRequest;
use App\Http\Requests\Admin\UpdateStockItemStatusRequest;
use App\Models\Admin;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Support\Admin\AdminShell;
use App\Support\Admin\Paginated;
use App\Support\Admin\StockPresenter;
use App\Support\Admin\StockQueries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operational stock and supplies (BR-09).
 */
class InventoryController extends Controller
{
    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.inventory.read');

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $filters = StockQueries::filters($request);

        return Inertia::render('admin/Inventory', [
            ...$adminShell->props($admin, 'Stock Inventory', 'inventory'),
            'items' => fn (): array => Paginated::fromPaginator(
                StockQueries::page($filters),
                fn (StockItem $item): array => StockPresenter::item($item),
            ),
            'movements' => fn (): array => Paginated::fromPaginator(
                StockQueries::movementPage($filters),
                fn (StockMovement $movement): array => StockPresenter::movement($movement),
            ),
            'stats' => fn (): array => StockQueries::stats(),
            'itemOptions' => fn (): array => StockQueries::itemOptions(),
            'filters' => $filters,
            'categories' => fn (): array => StockQueries::categoryOptions(),
            'suppliers' => fn (): array => StockQueries::supplierOptions(),
            'movementTypes' => StockQueries::MOVEMENT_TYPES,
            'statusFilters' => StockQueries::STATUS_FILTERS,
            'stockFilters' => StockQueries::STOCK_FILTERS,
            'capabilities' => [
                'create' => Gate::allows('admin.inventory.create'),
                'update' => Gate::allows('admin.inventory.update'),
            ],
        ]);
    }

    public function store(StoreStockItemRequest $request, SaveStockItem $saveStockItem): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        $saveStockItem->handle($request->item(), admin: $admin);

        return to_route('admin.inventory.index')
            ->with('success', 'Item stok berhasil ditambahkan.');
    }

    public function update(
        UpdateStockItemRequest $request,
        StockItem $stockItem,
        SaveStockItem $saveStockItem,
    ): RedirectResponse {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        $saveStockItem->handle($request->item(), $stockItem, $admin);

        return back()->with('success', 'Item stok berhasil diperbarui.');
    }

    public function updateStatus(UpdateStockItemStatusRequest $request, StockItem $stockItem): RedirectResponse
    {
        $stockItem->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Status item stok berhasil diperbarui.');
    }

    public function storeMovement(
        StoreStockMovementRequest $request,
        StockItem $stockItem,
        RecordStockMovement $recordStockMovement,
    ): RedirectResponse {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        $recordStockMovement->handle($stockItem, $request->movement(), $admin);

        return back()->with('success', 'Pergerakan stok berhasil dicatat.');
    }
}
