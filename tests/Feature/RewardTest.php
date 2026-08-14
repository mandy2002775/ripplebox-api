<?php

namespace Tests\Feature;

use App\Models\Salon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_salon_can_create_a_reward(): void
    {
        $salon = Salon::factory()->create();
        Sanctum::actingAs($salon->user);

        $response = $this->postJson('/api/rewards', [
            'reward_type' => 'gift_card',
            'reward_value' => 100,
            'description' => '$100 gift card for you and your friend',
            'recipient_type' => 'both',
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('rewards', [
            'salon_id' => $salon->id,
            'description' => '$100 gift card for you and your friend',
        ]);
    }

    public function test_a_salon_can_pause_a_reward_it_owns(): void
    {
        $salon = Salon::factory()->create();
        $reward = \App\Models\Reward::factory()->for($salon)->create();
        Sanctum::actingAs($salon->user);

        $response = $this->patchJson("/api/rewards/{$reward->id}", ['is_active' => false]);

        $response->assertOk();
        $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'is_active' => false]);
    }

    public function test_a_salon_cannot_edit_another_salons_reward(): void
    {
        $owner = Salon::factory()->create();
        $intruder = Salon::factory()->create();
        $reward = \App\Models\Reward::factory()->for($owner)->create();
        Sanctum::actingAs($intruder->user);

        $response = $this->patchJson("/api/rewards/{$reward->id}", ['is_active' => false]);

        $response->assertForbidden();
    }
}
