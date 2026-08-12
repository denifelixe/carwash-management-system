<?php

namespace App\Http\Controllers\Carwash;

use App\Http\Controllers\Controller;
use App\Support\Carwash\Brand;
use App\Support\Carwash\RoleAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Base for every admin module page, supplying the shell props the sidebar and
 * topbar need on all of them.
 */
abstract class AdminController extends Controller
{
    /**
     * @param  array<string, mixed>  $props
     */
    protected function page(Request $request, string $component, array $props = []): Response
    {
        $role = (string) $request->session()->get(RoleAccess::SESSION_KEY, RoleAccess::DEFAULT_ROLE);

        return Inertia::render($component, array_merge([
            'brand' => Brand::identity(),
            'notifications' => Brand::notifications(),
            'role' => RoleAccess::role($role),
            'modules' => RoleAccess::modulesFor($role),
            'persona' => RoleAccess::personaFor()[$role],
        ], $props));
    }
}
