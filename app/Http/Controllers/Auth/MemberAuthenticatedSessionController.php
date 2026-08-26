<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MemberLoginRequest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MemberAuthenticatedSessionController extends Controller
{
    /**
     * Show the member login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/MemberLogin', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Authenticate a member session.
     */
    public function store(MemberLoginRequest $request): RedirectResponse
    {
        if (! Auth::guard('member')->attempt($request->credentials(), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak sesuai.',
            ]);
        }

        $request->session()->regenerate();

        $member = Auth::guard('member')->user();

        if ($member instanceof Member) {
            $member->forceFill(['last_login_at' => now()])->saveQuietly();
        }

        return redirect()->intended(route('member.dashboard', absolute: false));
    }

    /**
     * Destroy the member session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('member')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('member.login');
    }
}
