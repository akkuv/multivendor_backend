<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UploadImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_an_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/uploads/images', [
                'image' => UploadedFile::fake()->image('photo.png', 8, 8),
            ])
            ->assertCreated()
            // Root-relative so the stored path stays portable across hosts; the
            // frontend resolves it against the API origin reported as asset_base.
            ->assertJsonPath('data.url', fn (string $url) => str_starts_with($url, '/uploads/images/'))
            ->assertJsonPath('asset_base', fn (string $base) => $base !== '');
    }

    public function test_guest_cannot_upload(): void
    {
        $this->postJson('/api/v1/uploads/images', [
            'image' => UploadedFile::fake()->image('photo.png', 8, 8),
        ])->assertUnauthorized();
    }

    public function test_non_image_file_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/uploads/images', [
                'image' => UploadedFile::fake()->create('doc.txt', 10),
            ])
            ->assertUnprocessable();
    }
}
