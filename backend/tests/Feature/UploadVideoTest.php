<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UploadVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_a_video(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/uploads/videos', [
                'video' => UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4'),
            ])
            ->assertCreated()
            // Root-relative so the stored path stays portable across hosts; the
            // frontend resolves it against the API origin reported as asset_base.
            ->assertJsonPath('data.url', fn (string $url) => str_starts_with($url, '/uploads/videos/'))
            ->assertJsonPath('asset_base', fn (string $base) => $base !== '');
    }

    public function test_guest_cannot_upload_a_video(): void
    {
        $this->postJson('/api/v1/uploads/videos', [
            'video' => UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4'),
        ])->assertUnauthorized();
    }

    public function test_non_video_file_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/uploads/videos', [
                'video' => UploadedFile::fake()->create('doc.txt', 10, 'text/plain'),
            ])
            ->assertUnprocessable();
    }

    public function test_video_larger_than_fifty_megabytes_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/uploads/videos', [
                // 51 MB exceeds the 50 MB cap shared with the PHP layer.
                'video' => UploadedFile::fake()->create('huge.mp4', 51 * 1024, 'video/mp4'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('video');
    }

    public function test_video_at_the_fifty_megabyte_cap_is_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/uploads/videos', [
                'video' => UploadedFile::fake()->create('cap.mp4', 50 * 1024, 'video/mp4'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.url', fn (string $url) => str_starts_with($url, '/uploads/videos/'));
    }

    public function test_video_truncated_by_php_upload_limit_names_the_cap(): void
    {
        $user = User::factory()->create();

        // A file above upload_max_filesize reaches PHP truncated and flagged
        // UPLOAD_ERR_INI_SIZE; its size never reaches the `max` rule, so the
        // controller reports the cap directly instead of a vague failed upload.
        $truncated = new UploadedFile(
            UploadedFile::fake()->create('truncated.mp4', 1, 'video/mp4')->getPathname(),
            'truncated.mp4',
            'video/mp4',
            UPLOAD_ERR_INI_SIZE,
            test: true,
        );

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/uploads/videos', ['video' => $truncated])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('video')
            ->assertJsonPath('errors.video.0', 'The video may not be greater than 50 MB.');
    }
}
