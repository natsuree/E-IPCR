<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Creates the first Administrator. The password is typed in, never stored in code or logs. */
class CreateAdmin extends Command
{
    protected $signature = 'eipcr:create-admin';

    protected $description = 'Create an E-IPCR Administrator account (asks for name, email and password)';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Full name'));
        $email = strtolower(trim((string) $this->ask('Email')));
        $password = (string) $this->secret('Password (at least 8 characters)');
        $confirmation = (string) $this->secret('Type the password again');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(8)],
            ],
            ['email.unique' => 'An account with this email already exists.'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => Role::Admin,
            'position' => 'System Administrator',
            'active' => true,
        ]);

        Audit::log('user.created', "Administrator account {$user->name} ({$user->email}) was created from the command line.", $user, by: $user);
        $this->info("Administrator {$user->name} <{$user->email}> was created. Sign in at ".route('login'));

        return self::SUCCESS;
    }
}
