<?php

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalonTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_salon_account_can_create_its_business_profile(): void
    {
        $user = User::factory()->salon()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/salons', [
            'business_name' => 'Gloss Hair Studio',
            'location' => '42 King St, Perth WA',
            'logo_url' => 'https://example.com/logo.png',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('salons', [
            'user_id' => $user->id,
            'business_name' => 'Gloss Hair Studio',
            'logo_url' => 'https://example.com/logo.png',
        ]);
    }

    public function test_a_client_account_cannot_create_a_business_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/salons', [
            'business_name' => 'Gloss Hair Studio',
            'location' => '42 King St, Perth WA',
        ]);

        $response->assertUnprocessable();
    }

    public function test_a_salon_cannot_create_a_second_business_profile(): void
    {
        $salon = Salon::factory()->create();
        Sanctum::actingAs($salon->user);

        $response = $this->postJson('/api/salons', [
            'business_name' => 'Second Salon',
            'location' => 'Somewhere else',
        ]);

        $response->assertStatus(409);
    }

    public function test_the_salon_list_includes_the_logo_url_for_client_side_discovery(): void
    {
        $salon = Salon::factory()->create(['logo_url' => 'https://example.com/logo.png']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/salons');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $salon->id, 'logo_url' => 'https://example.com/logo.png']);
    }

    public function test_the_salon_list_includes_the_highest_value_active_reward(): void
    {
        $salon = Salon::factory()->create();
        Reward::factory()->for($salon)->create([
            'description' => 'Free blowdry',
            'reward_value' => 30,
            'is_active' => true,
        ]);
        Reward::factory()->for($salon)->create([
            'description' => '$100 gift card',
            'reward_value' => 100,
            'is_active' => true,
        ]);
        Reward::factory()->for($salon)->create([
            'description' => 'Paused reward',
            'reward_value' => 500,
            'is_active' => false,
        ]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/salons');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $salon->id, 'top_reward' => '$100 gift card']);
    }

    public function test_the_salon_list_shows_no_top_reward_when_none_are_active(): void
    {
        $salon = Salon::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/salons');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $salon->id, 'top_reward' => null]);
    }

    public function test_a_salon_owner_can_edit_their_existing_profile(): void
    {
        $salon = Salon::factory()->create(['business_name' => 'Old Name']);
        Sanctum::actingAs($salon->user);

        $response = $this->patchJson('/api/salons', [
            'business_name' => 'New Name',
            'instagram_handle' => '@newname',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('salons', [
            'id' => $salon->id,
            'business_name' => 'New Name',
            'instagram_handle' => '@newname',
        ]);
    }

    public function test_a_salon_cannot_edit_a_profile_that_does_not_exist_yet(): void
    {
        $user = User::factory()->salon()->create();
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/salons', [
            'business_name' => 'New Name',
        ]);

        $response->assertUnprocessable();
    }

    public function test_a_salon_can_set_its_category_on_creation(): void
    {
        $user = User::factory()->salon()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/salons', [
            'business_name' => 'Gloss Hair Studio',
            'category' => 'hair',
            'location' => '42 King St, Perth WA',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('salons', ['user_id' => $user->id, 'category' => 'hair']);
    }

    public function test_an_invalid_category_is_rejected(): void
    {
        $user = User::factory()->salon()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/salons', [
            'business_name' => 'Gloss Hair Studio',
            'category' => 'not_a_real_category',
            'location' => '42 King St, Perth WA',
        ]);

        $response->assertUnprocessable();
    }

    public function test_the_salon_list_can_be_filtered_by_category(): void
    {
        $hairSalon = Salon::factory()->create(['category' => 'hair']);
        Salon::factory()->create(['category' => 'nails']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/salons?category=hair');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['id' => $hairSalon->id]);
    }

    public function test_a_salon_owner_can_change_their_category(): void
    {
        $salon = Salon::factory()->create(['category' => 'hair']);
        Sanctum::actingAs($salon->user);

        $response = $this->patchJson('/api/salons', ['category' => 'spa']);

        $response->assertOk();
        $this->assertDatabaseHas('salons', ['id' => $salon->id, 'category' => 'spa']);
    }
}
