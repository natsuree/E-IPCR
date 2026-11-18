<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * "My IPCRs" pages: open to employees, and to any other account that owns at
 * least one IPCR (for example an employee later made Department Head), so they
 * can view, correct and resubmit it. Which IPCR they may open is decided by
 * IpcrPolicy (owner only). Creating a new IPCR stays employee-only.
 */
class EnsureIpcrOwnerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account is inactive. Contact the Administrator.']);
        }

        abort_unless($user->isEmployee() || $user->ipcrs()->exists(), 403, 'This page belongs to another role.');

        return $next($request);
    }
}
