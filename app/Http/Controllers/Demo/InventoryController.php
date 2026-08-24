<?php

namespace App\Http\Controllers\Demo;

use App\Support\Demo\Inventory;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Operational stock management (BR-09).
 */
class InventoryController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'demo/admin/Inventory', [
            'items' => Inventory::items(),
            'movements' => Inventory::movements(),
            'categories' => Inventory::categories(),
            'movementTypes' => Inventory::movementTypes(),
        ]);
    }
}
