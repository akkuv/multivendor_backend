<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_ask_and_see_visible_questions(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/user/products/{$product->id}/questions", ['question' => 'Is it genuine?'])
            ->assertOk()
            ->assertJsonPath('data.question', 'Is it genuine?');

        ProductQuestion::factory()->create(['product_id' => $product->id, 'question' => 'Hidden one', 'is_hidden' => true]);

        $this->getJson("/api/v1/products/{$product->id}/questions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.question', 'Is it genuine?');
    }

    public function test_unauthored_user_cannot_ask_question(): void
    {
        $product = Product::factory()->create();

        $this->postJson("/api/v1/user/products/{$product->id}/questions", ['question' => 'Hello?'])
            ->assertStatus(401);
    }

    public function test_vendor_can_answer_own_product_question(): void
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $vendorUser = $vendor->user;
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);

        $question = ProductQuestion::factory()->create(['product_id' => $product->id]);

        Sanctum::actingAs($vendorUser);

        $this->postJson("/api/v1/vendor/questions/{$question->id}/answer", ['answer' => 'Yes, 100% genuine.'])
            ->assertOk()
            ->assertJsonPath('data.is_answered', true);

        $this->assertDatabaseHas('product_answers', ['product_question_id' => $question->id]);
    }

    public function test_vendor_cannot_answer_other_vendors_product_question(): void
    {
        $otherVendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $otherVendor->id]);
        $question = ProductQuestion::factory()->create(['product_id' => $product->id]);

        $vendor = VendorProfile::factory()->approved()->create();

        Sanctum::actingAs($vendor->user);

        $this->postJson("/api/v1/vendor/questions/{$question->id}/answer", ['answer' => 'Nope.'])
            ->assertStatus(403);
    }

    public function test_admin_can_moderate_question_full_cycle(): void
    {
        $product = Product::factory()->create();
        $question = ProductQuestion::factory()->create(['product_id' => $product->id]);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/v1/admin/questions?status=unanswered')->assertOk()->assertJsonCount(1, 'data.data');

        $this->postJson("/api/v1/admin/questions/{$question->id}/report", ['reason' => 'Spam'])
            ->assertOk()
            ->assertJsonPath('data.report_reason', 'Spam');

        $this->getJson('/api/v1/admin/questions?status=reported')->assertOk()->assertJsonCount(1, 'data.data');

        $this->putJson("/api/v1/admin/questions/{$question->id}/hide")
            ->assertOk()
            ->assertJsonPath('data.is_hidden', true);

        $this->deleteJson("/api/v1/admin/questions/{$question->id}")->assertOk();

        $this->assertDatabaseMissing('product_questions', ['id' => $question->id]);
    }

    public function test_customer_can_delete_only_own_question(): void
    {
        $product = Product::factory()->create();
        $other = User::factory()->create();
        $question = ProductQuestion::factory()->create(['product_id' => $product->id, 'user_id' => $other->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/v1/user/questions/{$question->id}")->assertStatus(403);
    }
}
