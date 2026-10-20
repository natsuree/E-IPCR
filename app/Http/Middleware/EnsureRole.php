<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:teaching,nonteaching'). Any other role gets 403.
 * Deactivated accounts are signed out.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
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

        abort_unless(in_array($user->role->value, $roles, true), 403, 'This page belongs to another role.');

        return $next($request);
    }
}
