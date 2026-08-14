<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Reward;
use App\Models\Salon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_can_redeem_a_friends_referral_code(): void
    {
        $referrer = Client::factory()->create();
        $client = Client::factory()->create();
        $salon = Salon::factory()->create();

        Sanctum::actingAs($client->user);

        $response = $this->postJson('/api/referrals', [
            'referral_code' => $referrer->referral_code,
            'salon_id' => $salon->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('referrals', [
            'referrer_client_id' => $referrer->id,
            'referred_client_id' => $client->id,
            'salon_id' => $salon->id,
            'status' => 'pending',
        ]);
    }

    public function test_a_client_cannot_redeem_their_own_code(): void
    {
        $client = Client::factory()->create();
        $salon = Salon::factory()->create();

        Sanctum::actingAs($client->user);

        $response = $this->postJson('/api/referrals', [
            'referral_code' => $client->referral_code,
            'salon_id' => $salon->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_the_same_code_cannot_be_redeemed_twice_at_the_same_salon(): void
    {
        $referrer = Client::factory()->create();
        $client = Client::factory()->create();
        $salon = Salon::factory()->create();

        Sanctum::actingAs($client->user);

        $this->postJson('/api/referrals', [
            'referral_code' => $referrer->referral_code,
            'salon_id' => $salon->id,
        ])->assertCreated();

        $response = $this->postJson('/api/referrals', [
            'referral_code' => $referrer->referral_code,
            'salon_id' => $salon->id,
        ]);

        $response->assertStatus(409);
    }

    public function test_the_owning_salon_can_complete_a_pending_referral_and_pay_out_a_reward(): void
    {
        $referrer = Client::factory()->create();
        $client = Client::factory()->create();
        $salon = Salon::factory()->create();
        $reward = Reward::factory()->for($salon)->create();

        Sanctum::actingAs($client->user);
        $this->postJson('/api/referrals', [
            'referral_code' => $referrer->referral_code,
            'salon_id' => $salon->id,
        ])->assertCreated();

        $referral = $salon->referrals()->first();

        Sanctum::actingAs($salon->user);
        $response = $this->patchJson("/api/referrals/{$referral->id}/complete", [
            'reward_id' => $reward->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('referrals', [
            'id' => $referral->id,
            'status' => 'redeemed',
        ]);
        $this->assertDatabaseHas('redemptions', [
            'referral_id' => $referral->id,
            'reward_id' => $reward->id,
        ]);
    }

    public function test_a_different_salon_cannot_complete_someone_elses_referral(): void
    {
        $referrer = Client::factory()->create();
        $client = Client::factory()->create();
        $salon = Salon::factory()->create();
        $otherSalon = Salon::factory()->create();
        $reward = Reward::factory()->for($salon)->create();

        Sanctum::actingAs($client->user);
        $this->postJson('/api/referrals', [
            'referral_code' => $referrer->referral_code,
            'salon_id' => $salon->id,
        ])->assertCreated();

        $referral = $salon->referrals()->first();

        Sanctum::actingAs($otherSalon->user);
        $response = $this->patchJson("/api/referrals/{$referral->id}/complete", [
            'reward_id' => $reward->id,
        ]);

        $response->assertForbidden();
    }

    public function test_completing_a_referral_makes_it_show_up_in_both_clients_dashboards(): void
    {
        $referrer = Client::factory()->create();
        $client = Client::factory()->create();
        $salon = Salon::factory()->create();
        $reward = Reward::factory()->for($salon)->create(['reward_value' => 75]);

        Sanctum::actingAs($client->user);
        $this->postJson('/api/referrals', [
            'referral_code' => $referrer->referral_code,
            'salon_id' => $salon->id,
        ])->assertCreated();

        $referral = $salon->referrals()->first();

        Sanctum::actingAs($salon->user);
        $this->patchJson("/api/referrals/{$referral->id}/complete", [
            'reward_id' => $reward->id,
        ])->assertOk();

        Sanctum::actingAs($referrer->user);
        $referrerDashboard = $this->getJson('/api/clients/dashboard')->assertOk();
        $referrerDashboard->assertJsonPath('rewards_count', 1);
        $referrerDashboard->assertJsonPath('earned', 75);

        Sanctum::actingAs($client->user);
        $clientDashboard = $this->getJson('/api/clients/dashboard')->assertOk();
        $clientDashboard->assertJsonPath('rewards_count', 1);
    }
}
