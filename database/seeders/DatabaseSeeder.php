<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Only for a brand new database. Once any user exists, this seeder never changes
        // a password and never adds a user.
        if (User::query()->exists()) {
            return;
        }

        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Admin@12345'), // temporary: change it with "php artisan admin:password"
        ]);
    }
}