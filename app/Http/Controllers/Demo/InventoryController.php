<?php

namespace App\Http\Controllers\Demo;

use App\Support\Admin\StockQueries;
use App\Support\Demo\Inventory;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Operational stock management (BR-09), sharing the live module page.
 */
class InventoryController extends AdminController
{
    public function index(Request $request): Response
    {
        $filters = StockQueries::filters($request);

        return $this->page($request, 'admin/Inventory', [
            'items' => Inventory::page($filters),
            'movements' => Inventory::movementPage($filters),
            'stats' => Inventory::stats(),
            'itemOptions' => Inventory::itemOptions(),
            'filters' => $filters,
            'categories' => Inventory::categories(),
            'suppliers' => Inventory::suppliers(),
            'movementTypes' => Inventory::movementTypes(),
            'statusFilters' => StockQueries::STATUS_FILTERS,
            'stockFilters' => StockQueries::STOCK_FILTERS,
            'capabilities' => ['create' => true, 'update' => true],
        ]);
    }
}
