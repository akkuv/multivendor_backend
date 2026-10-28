<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\VendorStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_registration_creates_pending_vendor(): void
    {
        $payload = [
            'name' => 'Vendor User',
            'email' => 'vendor@example.com',
            'phone' => '9999999999',
            'password' => 'password',
            'password_confirmation' => 'password',
            'store_name' => 'Vendor Store',
            'address_line1' => '123 Market Street',
            'gstin' => 'GST1234',
            'pan' => 'PAN1234',
            'bank_account_name' => 'Vendor User',
            'bank_account_number' => '1234567890',
            'bank_ifsc' => 'IFSC0001',
            'bank_name' => 'Test Bank',
            'upi_id' => 'vendor@upi',
        ];

        $response = $this->postJson('/api/v1/auth/vendor/register', $payload)
            ->assertCreated()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'email' => 'vendor@example.com',
            'role' => Role::Vendor->value,
        ]);

        $user = User::where('email', 'vendor@example.com')->first();
        $this->assertSame(VendorStatus::Pending->value, $user->vendorProfile->status);
        $this->assertFalse($user->vendorProfile->is_approved);

        $this->assertArrayHasKey('token', $response->json('data'));
    }

    public function test_vendor_registration_validates_required_fields(): void
    {
        $this->postJson('/api/v1/auth/vendor/register', [])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_vendor_registration_requires_unique_email(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);

        $payload = [
            'name' => 'Vendor User',
            'email' => 'taken@example.com',
            'phone' => '9999999999',
            'password' => 'password',
            'password_confirmation' => 'password',
            'store_name' => 'Vendor Store',
            'address_line1' => '123 Market Street',
            'gstin' => 'GST1234',
            'pan' => 'PAN1234',
            'bank_account_name' => 'Vendor User',
            'bank_account_number' => '1234567890',
            'bank_ifsc' => 'IFSC0001',
            'bank_name' => 'Test Bank',
        ];

        $this->postJson('/api/v1/auth/vendor/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseMissing('vendor_profiles', ['user_id' => $existing->id]);
    }

    public function test_vendor_panel_blocks_pending_vendor(): void
    {
        $user = User::factory()->vendor()->create();
        $user->vendorProfile()->create([
            'store_name' => 'Pending Store',
            'slug' => 'pending-store',
            'status' => VendorStatus::Pending->value,
            'is_approved' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/vendor/dashboard')
            ->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    public function test_vendor_panel_allows_approved_vendor(): void
    {
        $user = User::factory()->vendor()->create();
        $user->vendorProfile()->create([
            'store_name' => 'Approved Store',
            'slug' => 'approved-store',
            'status' => VendorStatus::Approved->value,
            'is_approved' => true,
            'commission_rate' => 10,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/vendor/dashboard')
            ->assertOk();
    }

    public function test_vendor_panel_blocks_customer(): void
    {
        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/vendor/dashboard')
            ->assertStatus(403);
    }
}
