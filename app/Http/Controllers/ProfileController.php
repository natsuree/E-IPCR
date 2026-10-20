<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.show', ['user' => $request->user()->load(['college', 'department'])]);
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->update(['password' => $request->validated('password')]);
        Audit::log('auth.password_changed', "{$user->name} changed their password.", $user);

        return back()->with('status', 'Your password was changed.');
    }
}
