<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Creates the first admin (owner) from environment variables, with no default account or PIN in the code.
 * It is NOT called by DatabaseSeeder, so demo and test seeding never makes an admin. Run it on purpose:
 *
 *   ADMIN_EMAIL=you@example.com ADMIN_NAME="Your Name" ADMIN_PIN=<6 digits> ADMIN_PASSWORD=<password> php artisan db:seed --class=AdminSeeder
 *
 * The values come from the environment (Railway variables) and never from this file, because this file is in git.
 * Then remove the variables. Without ADMIN_PASSWORD a new account gets a random password: use "Forgot password".
 * Running it again for the same email refreshes the PIN and the owner role (and the password, if ADMIN_PASSWORD is set).
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // getenv, not env(): env() returns null outside config files once the config is cached
        $email = getenv('ADMIN_EMAIL') ?: null;
        $name = getenv('ADMIN_NAME') ?: null;

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command->error('Set ADMIN_EMAIL (a real email address). No admin was created.');

            return;
        }

        if (! getenv('ADMIN_PIN')) {
            $this->command->error('Set ADMIN_PIN (6 digits). No admin was created.');

            return;
        }

        // admin:make reads ADMIN_PIN itself, so the PIN never passes through this file
        $code = Artisan::call('admin:make', ['email' => $email, '--name' => $name], $this->command->getOutput());

        if ($code !== 0) {
            $this->command->error('The admin was not created. See the message above.');
        }
    }
}
