<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\password;

/** Creates (or promotes) an owner. The PIN is asked for with a hidden prompt, or read from ADMIN_PIN, so it never sits in git. */
class AdminMake extends Command
{
    protected $signature = 'admin:make {email} {--name= : Name for a new account} {--role=owner : owner or support}';

    protected $description = 'Make a user an admin (owner or support); creates the account if the email is new';

    public function handle(): int
    {
        $role = $this->option('role');
        if (! in_array($role, ['owner', 'support'], true)) {
            $this->error('Role must be owner or support.');

            return 1;
        }

        $user = User::firstWhere('email', $this->argument('email'));

        if (! $user && ! $this->option('name')) {
            $this->error('No user with that email. Pass --name="Full Name" to create one.');

            return 1;
        }

        $pin = getenv('ADMIN_PIN') ?: password('6-digit PIN for the admin gate', required: true);
        if (! preg_match('/^\d{6}$/', $pin)) {
            $this->error('The PIN must be exactly 6 digits.');

            return 1;
        }

        if (! $user) {
            $user = new User(['name' => $this->option('name'), 'email' => $this->argument('email'), 'password' => Hash::make(str()->random(40))]);
            $user->save();
            $this->warn('New account: use "Forgot password" on the login page to set its password.');
        }

        // admin_role and pin are not mass-assignable on purpose, so they are set here and nowhere in a request
        $user->forceFill(['admin_role' => $role, 'pin' => Hash::make($pin), 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        $this->info("{$user->email} is now an admin ({$role}).");

        return 0;
    }
}
