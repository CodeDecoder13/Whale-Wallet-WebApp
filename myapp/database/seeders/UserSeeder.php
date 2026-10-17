<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrNew(['email' => 'boyparamio@gmail.com']);
        $user->forceFill([
            'name' => 'Rhuzzel Paramio',
            'password' => 'P@ssw0rd12', // hashed by the model cast
            'email_verified_at' => now(),
        ])->save();
    }
}
