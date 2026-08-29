<?php

namespace App\Http\Controllers\Demo;

use App\Support\Demo\RoleAccess;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Staff accounts and their role permissions (BR-11).
 */
class AdminRoleController extends AdminController
{
    public function index(Request $request): Response
    {
        return $this->page($request, 'admin/Users', RoleAccess::userRoleProps());
    }
}
