<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ContentPost;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_a_salon_can_upload_a_content_post(): void
    {
        $salon = Salon::factory()->create();
        Sanctum::actingAs($salon->user);

        $response = $this->postJson('/api/content', [
            'image' => UploadedFile::fake()->create('salon.jpg', 100, 'image/jpeg'),
            'caption' => 'Fresh cut, fresh look',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('caption', 'Fresh cut, fresh look');
        $this->assertDatabaseHas('content_posts', ['salon_id' => $salon->id]);
    }

    public function test_a_salon_without_a_profile_cannot_upload_content(): void
    {
        $user = User::factory()->salon()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/content', [
            'image' => UploadedFile::fake()->create('salon.jpg', 100, 'image/jpeg'),
        ])->assertUnprocessable();
    }

    public function test_a_salon_can_list_and_delete_its_own_post(): void
    {
        $salon = Salon::factory()->create();
        Sanctum::actingAs($salon->user);

        $upload = $this->postJson('/api/content', [
            'image' => UploadedFile::fake()->create('salon.jpg', 100, 'image/jpeg'),
        ])->json();

        $this->getJson('/api/content')->assertJsonCount(1);

        $this->deleteJson("/api/content/{$upload['id']}")->assertOk();
        $this->assertSoftDeleted('content_posts', ['id' => $upload['id']]);
    }

    public function test_a_salon_cannot_delete_another_salons_post(): void
    {
        $owner = Salon::factory()->create();
        Sanctum::actingAs($owner->user);
        $post = ContentPost::create([
            'salon_id' => $owner->id,
            'image_path' => 'content/fake.jpg',
            'image_mime' => 'image/jpeg',
        ]);

        $otherSalon = Salon::factory()->create();
        Sanctum::actingAs($otherSalon->user);

        $this->deleteJson("/api/content/{$post->id}")->assertForbidden();
    }

    public function test_a_client_can_view_a_salons_content_and_like_it(): void
    {
        $salon = Salon::factory()->create();
        $post = ContentPost::create([
            'salon_id' => $salon->id,
            'image_path' => 'content/fake.jpg',
            'image_mime' => 'image/jpeg',
        ]);

        $client = Client::factory()->create();
        Sanctum::actingAs($client->user);

        $list = $this->getJson("/api/salons/{$salon->id}/content");
        $list->assertOk();
        $list->assertJsonPath('0.liked_by_me', false);
        $list->assertJsonPath('0.likes_count', 0);

        $like = $this->postJson("/api/content/{$post->id}/like");
        $like->assertOk();
        $like->assertJsonPath('liked', true);
        $like->assertJsonPath('likes_count', 1);

        // Liking again toggles it back off.
        $unlike = $this->postJson("/api/content/{$post->id}/like");
        $unlike->assertJsonPath('liked', false);
        $unlike->assertJsonPath('likes_count', 0);
    }

    public function test_a_salon_account_cannot_like_content(): void
    {
        $salon = Salon::factory()->create();
        $post = ContentPost::create([
            'salon_id' => $salon->id,
            'image_path' => 'content/fake.jpg',
            'image_mime' => 'image/jpeg',
        ]);

        $otherSalon = Salon::factory()->create();
        Sanctum::actingAs($otherSalon->user);

        $this->postJson("/api/content/{$post->id}/like")->assertUnprocessable();
    }
}
