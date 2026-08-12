<?php

namespace App\Http\Controllers\Carwash;

use App\Http\Controllers\Controller;
use App\Support\Carwash\Brand;
use App\Support\Carwash\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Demo entry point: pick the staff role to sign in as, or open the customer
 * portal. The prototype stores the choice in the session instead of a database.
 */
class EntryController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('carwash/auth/Entry', [
            'brand' => Brand::identity(),
            'roles' => RoleAccess::roles(),
            'matrix' => RoleAccess::matrix(),
            'modules' => RoleAccess::modules(),
            'activeRole' => $request->session()->get(RoleAccess::SESSION_KEY),
        ]);
    }

    /**
     * Switch the active demo role and land on that role's first module.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', array_keys(RoleAccess::matrix()))],
        ]);

        $request->session()->put(RoleAccess::SESSION_KEY, $validated['role']);

        return to_route(RoleAccess::homeRouteFor($validated['role']));
    }

    /**
     * Leave the admin console and return to the role picker.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(RoleAccess::SESSION_KEY);

        return to_route('carwash.entry');
    }
}
