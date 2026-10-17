<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_admin_upload_requires_authentication(): void
    {
        $this->postJson('/api/v1/admin/uploads', [
            'file' => UploadedFile::fake()->image('service.jpg'),
            'folder' => 'services',
        ])->assertStatus(401);
    }

    public function test_admin_upload_accepts_a_valid_image_and_folder(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/admin/uploads', [
            'file' => UploadedFile::fake()->image('service.jpg'),
            'folder' => 'services',
        ]);

        $response->assertCreated()->assertJsonStructure(['path']);
    }

    public function test_admin_upload_rejects_an_unknown_folder(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/admin/uploads', [
            'file' => UploadedFile::fake()->image('service.jpg'),
            'folder' => 'not-a-real-folder',
        ])->assertStatus(422);
    }

    public function test_admin_upload_rejects_an_oversized_image(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/admin/uploads', [
            'file' => UploadedFile::fake()->image('service.jpg')->size(9000),
            'folder' => 'services',
        ])->assertStatus(422);
    }

    public function test_admin_upload_accepts_a_valid_video_in_the_video_folder(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/admin/uploads', [
            'file' => UploadedFile::fake()->create('process.mp4', 20000, 'video/mp4'),
            'folder' => 'service-videos',
        ]);

        $response->assertCreated()->assertJsonStructure(['path']);
    }

    public function test_admin_upload_rejects_an_image_file_for_the_video_folder(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/admin/uploads', [
            'file' => UploadedFile::fake()->image('service.jpg'),
            'folder' => 'service-videos',
        ])->assertStatus(422);
    }

    public function test_admin_upload_rejects_an_oversized_video(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/admin/uploads', [
            'file' => UploadedFile::fake()->create('process.mp4', 90000, 'video/mp4'),
            'folder' => 'service-videos',
        ])->assertStatus(422);
    }
}
