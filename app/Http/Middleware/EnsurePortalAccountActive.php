<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePortalAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        // Missing stamp maps to zero for sessions that predate this migration.
        // Any password reset / deactivation increments the revision and revokes them.
        if (! $user?->is_active ||
            (int) $request->session()->get('portal.auth_version', 0) !== (int) $user->portal_session_version) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
