<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Admin;
use App\Support\Admin\AdminShell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the admin's profile settings page.
     */
    public function edit(Request $request, AdminShell $adminShell): Response
    {
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin, 403);

        return Inertia::render('settings/Profile', $adminShell->props($admin, 'Pengaturan profil'));
    }

    /**
     * Update the admin's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin, 403);

        $admin->fill($request->validated());

        $admin->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('admin.profile.edit');
    }

    /**
     * Delete the admin's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin, 403);

        Auth::guard('admin')->logout();

        $admin->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('admin.login');
    }
}
