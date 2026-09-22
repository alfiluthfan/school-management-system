<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PortalAuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $identifier = trim($validated['identifier']);
        $key = 'portal-login:'.hash('sha256', mb_strtolower($identifier).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'identifier' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Reuse Laravel's web session guard; no personal access token in localStorage.
        $authenticated = Auth::guard('web')->attempt([
            $field => $identifier,
            'password' => $validated['password'],
            'is_active' => true,
        ], (bool) ($validated['remember'] ?? false));

        if (! $authenticated) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'identifier' => 'Email/username atau kata sandi tidak sesuai.',
            ]);
        }

        $user = Auth::guard('web')->user();
        if (! $user instanceof User || ! $user->hasAnyPermission([
            'dashboard.admin', 'dashboard.principal', 'dashboard.teacher',
            'dashboard.student', 'dashboard.parent',
        ])) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages([
                'identifier' => 'Akun belum memiliki akses portal. Hubungi administrator.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        // Save the current server-side revision after session ID regeneration.
        $request->session()->put('portal.auth_version', (int) $user->portal_session_version);
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('portal.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
