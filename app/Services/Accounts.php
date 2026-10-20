<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class Accounts
{
    /** Create an account with an unusable random password and email a set-password link. */
    public function create(array $data, User $by): User
    {
        $user = User::create([...$data, 'password' => Str::password(40)]);

        Audit::log('user.created', "{$by->name} created an account for {$user->name} ({$user->email}) as {$user->role->label()}.", $user, $this->safe($user->getAttributes()), $by);
        $this->sendSetPasswordLink($user, $by, false);

        return $user;
    }

    public function sendSetPasswordLink(User $user, User $by, bool $log = true): void
    {
        $token = Password::broker()->createToken($user);
        $user->notify(new SetPasswordNotification($token));

        if ($log) {
            Audit::log('user.link_sent', "{$by->name} sent a new set-password email to {$user->name} ({$user->email}).", $user, by: $by);
        }
    }

    public function safe(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(['password', 'remember_token', 'created_at', 'updated_at', 'deleted_at', 'id']));
    }
}
