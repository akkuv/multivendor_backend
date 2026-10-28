<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFaq;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductFaqTest extends TestCase
{
    use RefreshDatabase;

    protected function vendorUser(): User
    {
        $user = User::factory()->vendor()->create();
        VendorProfile::factory()->for($user)->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_vendor_can_create_product_with_faqs(): void
    {
        $this->vendorUser();
        $category = Category::factory()->create();

        $this->postJson('/api/v1/vendor/products', [
            'category_id' => $category->id,
            'name' => 'FAQ Product',
            'price' => 999,
            'stock' => 5,
            'faqs' => [
                ['question' => 'Is it machine washable?', 'answer' => 'Yes, cold wash only.'],
                ['question' => 'What is the warranty?', 'answer' => '6 months against manufacturing defects.'],
            ],
        ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.faqs')
            ->assertJsonPath('data.faqs.0.question', 'Is it machine washable?')
            ->assertJsonPath('data.faqs.1.answer', '6 months against manufacturing defects.');

        $product = Product::where('name', 'FAQ Product')->firstOrFail();

        $this->assertSame(2, $product->faqs()->count());
    }

    public function test_updating_a_product_replaces_its_faqs(): void
    {
        $user = $this->vendorUser();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'vendor_id' => $user->vendorProfile->id,
            'category_id' => $category->id,
        ]);

        ProductFaq::factory()->for($product)->create(['question' => 'Old?', 'answer' => 'Old.']);

        $this->putJson("/api/v1/vendor/products/{$product->id}", [
            'faqs' => [['question' => 'New?', 'answer' => 'New.']],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.faqs')
            ->assertJsonPath('data.faqs.0.question', 'New?');

        $this->assertSame(1, $product->faqs()->count());
        $this->assertSame('New?', $product->faqs()->first()->question);
    }

    public function test_faq_requires_an_answer(): void
    {
        $this->vendorUser();
        $category = Category::factory()->create();

        $this->postJson('/api/v1/vendor/products', [
            'category_id' => $category->id,
            'name' => 'Incomplete FAQ Product',
            'price' => 100,
            'stock' => 1,
            'faqs' => [['question' => 'Question with no answer?']],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('faqs.0.answer');
    }

    public function test_storefront_product_includes_faqs(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'status' => 'PUBLISHED',
            'approval_status' => 'APPROVED',
        ]);

        ProductFaq::factory()->for($product)->create([
            'question' => 'How long is delivery?',
            'answer' => '3-5 business days.',
            'sort_order' => 0,
        ]);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.faqs')
            ->assertJsonPath('data.faqs.0.question', 'How long is delivery?')
            ->assertJsonPath('data.faqs.0.answer', '3-5 business days.');
    }
}
