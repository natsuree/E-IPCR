<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        $samples = collect();

        // The sample-accounts panel exists only for local development.
        if (app()->environment('local')) {
            $emails = config('eipcr.sample_accounts', []);
            $samples = User::whereIn('email', $emails)->where('active', true)->with(['college', 'department'])->get()
                ->sortBy(fn (User $u) => array_search($u->email, $emails))
                ->values();
        }

        return view('auth.login', ['samples' => $samples]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect(Auth::user()->dashboardRoute());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have logged out.');
    }
}
