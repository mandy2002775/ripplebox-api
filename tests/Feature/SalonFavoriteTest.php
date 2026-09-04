<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Salon;
use App\Models\SalonFavorite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalonFavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_can_favorite_a_salon(): void
    {
        $client = Client::factory()->create();
        $salon = Salon::factory()->create();
        Sanctum::actingAs($client->user);

        $response = $this->postJson("/api/salons/{$salon->id}/favorite");

        $response->assertOk();
        $response->assertJsonPath('is_favorited', true);
        $this->assertDatabaseHas('salon_favorites', ['client_id' => $client->id, 'salon_id' => $salon->id]);
    }

    public function test_favoriting_twice_toggles_it_back_off(): void
    {
        $client = Client::factory()->create();
        $salon = Salon::factory()->create();
        Sanctum::actingAs($client->user);

        $this->postJson("/api/salons/{$salon->id}/favorite")->assertJsonPath('is_favorited', true);
        $response = $this->postJson("/api/salons/{$salon->id}/favorite");

        $response->assertJsonPath('is_favorited', false);
        $this->assertDatabaseMissing('salon_favorites', ['client_id' => $client->id, 'salon_id' => $salon->id]);
    }

    public function test_a_salon_account_cannot_favorite_salons(): void
    {
        $salon = Salon::factory()->create();
        $otherSalon = Salon::factory()->create();
        Sanctum::actingAs($salon->user);

        $this->postJson("/api/salons/{$otherSalon->id}/favorite")->assertUnprocessable();
    }

    public function test_a_clients_favorites_list_only_shows_their_own(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $favorited = Salon::factory()->create(['business_name' => 'Favorited Salon']);
        $notFavorited = Salon::factory()->create(['business_name' => 'Other Salon']);

        SalonFavorite::create(['client_id' => $client->id, 'salon_id' => $favorited->id]);
        SalonFavorite::create(['client_id' => $otherClient->id, 'salon_id' => $notFavorited->id]);

        Sanctum::actingAs($client->user);
        $response = $this->getJson('/api/salons/favorites');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['id' => $favorited->id]);
    }

    public function test_the_salon_list_flags_which_ones_the_client_has_favorited(): void
    {
        $client = Client::factory()->create();
        $favorited = Salon::factory()->create();
        $notFavorited = Salon::factory()->create();
        SalonFavorite::create(['client_id' => $client->id, 'salon_id' => $favorited->id]);

        Sanctum::actingAs($client->user);
        $response = $this->getJson('/api/salons');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $favorited->id, 'is_favorited' => true]);
        $response->assertJsonFragment(['id' => $notFavorited->id, 'is_favorited' => false]);
    }
}
