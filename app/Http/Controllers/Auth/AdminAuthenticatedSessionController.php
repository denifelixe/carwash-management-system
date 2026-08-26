<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuthenticatedSessionController extends Controller
{
    /**
     * Show the admin login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/AdminLogin', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Authenticate an admin session.
     */
    public function store(AdminLoginRequest $request): RedirectResponse
    {
        if (! Auth::guard('admin')->attempt($request->credentials(), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak sesuai.',
            ]);
        }

        $request->session()->regenerate();

        $admin = Auth::guard('admin')->user();

        if ($admin instanceof Admin) {
            $admin->forceFill(['last_login_at' => now()])->saveQuietly();
        }

        return redirect()->intended(route('admin.dashboard', absolute: false));
    }

    /**
     * Destroy the admin session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('admin.login');
    }
}
