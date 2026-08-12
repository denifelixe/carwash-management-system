<?php

namespace App\Http\Controllers\Carwash;

use App\Support\Carwash\Inventory;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Operational stock management (BR-09).
 */
class InventoryController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'carwash/admin/Inventory', [
            'items' => Inventory::items(),
            'movements' => Inventory::movements(),
            'categories' => Inventory::categories(),
            'movementTypes' => Inventory::movementTypes(),
        ]);
    }
}
