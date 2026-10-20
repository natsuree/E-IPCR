<?php

namespace App\Providers;

use App\Models\RatingPeriod;
use App\Services\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Header: the current rating period on every page that uses the main layout.
        View::composer('layouts.app', function ($view) {
            $view->with('currentPeriod', RatingPeriod::current());
        });

        // Sign-in events for the audit log. Passwords are never recorded.
        Event::listen(Login::class, fn (Login $e) => Audit::log('auth.login', "{$e->user->name} signed in.", $e->user, by: $e->user));
        Event::listen(Logout::class, fn (Logout $e) => $e->user && Audit::log('auth.logout', "{$e->user->name} signed out.", $e->user, by: $e->user));
        Event::listen(Failed::class, fn (Failed $e) => Audit::log(
            'auth.failed',
            'Failed sign-in attempt for '.($e->credentials['email'] ?? 'an unknown email').'.',
            $e->user,
        ));
        Event::listen(Lockout::class, fn (Lockout $e) => Audit::log(
            'auth.lockout',
            'Sign-in locked for a short time after too many attempts for '.$e->request->input('email').'.',
        ));
        Event::listen(PasswordReset::class, fn (PasswordReset $e) => Audit::log('auth.password_reset', "{$e->user->name} set a new password through an emailed link.", $e->user, by: $e->user));
    }
}
