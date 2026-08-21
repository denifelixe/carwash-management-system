<?php

namespace App\Http\Controllers\Carwash;

use App\Support\Carwash\RoleAccess;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Staff accounts and their role permissions (BR-11).
 */
class UserRoleController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'carwash/admin/Users', [
            'staff' => RoleAccess::staff(),
            'roles' => RoleAccess::roles(),
            'shifts' => RoleAccess::shifts(),
            'matrix' => RoleAccess::matrix(),
            'allModules' => RoleAccess::modules(),
        ]);
    }
}
