<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Models\Admin;
use App\Support\Admin\AdminShell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Show the admin's security settings page.
     */
    public function edit(Request $request, AdminShell $adminShell): Response
    {
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin, 403);

        return Inertia::render('settings/Security', [
            ...$adminShell->props($admin, 'Keamanan akun'),
        ]);
    }

    /**
     * Update the admin's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin, 403);

        $admin->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
