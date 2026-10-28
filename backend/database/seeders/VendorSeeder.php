<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\VendorStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VendorSeeder extends Seeder
{
    protected array $logos = [
        'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=200&h=200&fit=crop',
        'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=200&h=200&fit=crop',
        'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=200&h=200&fit=crop',
        'https://images.unsplash.com/photo-1524758631624-e2822e304c36?w=200&h=200&fit=crop',
        'https://images.unsplash.com/photo-1445527815219-ecbfec67492e?w=200&h=200&fit=crop',
    ];

    public function run(int $count = 5): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $user = User::query()->updateOrCreate(
                ['email' => "vendor{$i}@multivendor.test"],
                [
                    'name' => "Vendor {$i}",
                    'password' => Hash::make('password'),
                    'role' => Role::Vendor->value,
                    'phone' => '9'.str_pad((string) $i, 9, '0', STR_PAD_LEFT),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->vendorProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'store_name' => "Store {$i}",
                    'slug' => "store-{$i}-".Str::random(4),
                    'description' => "Store {$i} sells quality products.",
                    'logo' => $this->logos[($i - 1) % count($this->logos)],
                    'city' => 'Mumbai',
                    'state' => 'Maharashtra',
                    'country' => 'India',
                    'pincode' => '400001',
                    'commission_rate' => 10.00,
                    'is_approved' => true,
                    'status' => VendorStatus::Approved->value,
                ],
            );

            $user->wallet()->firstOrCreate(
                ['user_id' => $user->id],
                ['currency' => 'INR', 'available_balance' => 0, 'pending_balance' => 0, 'status' => 'ACTIVE'],
            );
        }
    }
}
