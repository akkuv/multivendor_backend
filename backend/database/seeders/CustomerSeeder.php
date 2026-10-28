<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    public function run(int $count = 10): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $user = User::query()->updateOrCreate(
                ['email' => "customer{$i}@multivendor.test"],
                [
                    'name' => "Customer {$i}",
                    'password' => Hash::make('password'),
                    'role' => Role::Customer->value,
                    'phone' => '8'.str_pad((string) $i, 9, '0', STR_PAD_LEFT),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->wallet()->firstOrCreate(
                ['user_id' => $user->id],
                ['currency' => 'INR', 'available_balance' => 0, 'pending_balance' => 0, 'status' => 'ACTIVE'],
            );
            $user->cart()->firstOrCreate(['user_id' => $user->id]);
        }
    }
}
