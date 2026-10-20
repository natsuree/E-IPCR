<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** "Forgot password" and the set/reset password page (also used by new-account emails). */
class PasswordController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(ForgotPasswordRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->validated('email'))->where('active', true)->first();

        if ($user) {
            $status = Password::sendResetLink(['email' => $user->email]);
            if ($status === Password::RESET_THROTTLED) {
                return back()->withInput()->withErrors(['email' => 'A reset link was sent a moment ago. Wait a minute before asking for another one.']);
            }
        }

        // Same answer whether or not the account exists, so emails cannot be guessed.
        return back()->with('status', 'If an active account uses that email, we sent a link to reset the password. Check your inbox and spam folder.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'This link is invalid or has expired, or the email does not match. Use "Forgot password" to get a new link.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Your password is set. You can now log in.');
    }
}
