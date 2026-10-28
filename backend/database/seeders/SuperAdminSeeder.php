<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('ADMIN_PASSWORD', '');

        if ($password === '') {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make($password),
                'role' => Role::SuperAdmin->value,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
