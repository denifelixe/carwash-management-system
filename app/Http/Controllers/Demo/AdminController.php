<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Support\Demo\Brand;
use App\Support\Demo\RoleAccess;
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
        $modules = array_map(
            fn (array $module): array => [
                ...$module,
                'href' => route($module['route'], absolute: false),
                'enabled' => true,
                'active' => $request->routeIs($module['route']),
            ],
            RoleAccess::modulesFor($role),
        );

        return Inertia::render($component, array_merge([
            'mode' => 'demo',
            'brand' => Brand::identity(),
            'notifications' => Brand::notifications(),
            'role' => RoleAccess::role($role),
            'modules' => $modules,
            'persona' => RoleAccess::personaFor()[$role],
            'profileHref' => null,
            'headerAction' => [
                'label' => 'Ganti role',
                'href' => route('demo.home', absolute: false),
                'method' => 'get',
            ],
            'exitAction' => [
                'label' => 'Keluar dari demo',
                'href' => route('demo.session.exit', absolute: false),
                'method' => 'post',
            ],
        ], $props));
    }
}
