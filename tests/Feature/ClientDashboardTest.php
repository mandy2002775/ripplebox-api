<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Redemption;
use App\Models\Referral;
use App\Models\Reward;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_without_a_client_profile_cannot_view_the_dashboard(): void
    {
        $user = User::factory()->create(['user_type' => 'salon']);
        Sanctum::actingAs($user);

        $this->getJson('/api/clients/dashboard')->assertUnprocessable();
    }

    public function test_the_dashboard_summarises_referrals_rewards_and_earnings(): void
    {
        $referrer = Client::factory()->create();
        $referred = Client::factory()->create();
        $salon = Salon::factory()->create();
        $reward = Reward::factory()->for($salon)->create(['reward_value' => 60]);
        $referral = Referral::create([
            'referrer_client_id' => $referrer->id,
            'referred_client_id' => $referred->id,
            'salon_id' => $salon->id,
            'status' => 'redeemed',
        ]);
        Redemption::create([
            'referral_id' => $referral->id,
            'reward_id' => $reward->id,
            'redeemed_at' => now(),
        ]);

        Sanctum::actingAs($referrer->user);
        $response = $this->getJson('/api/clients/dashboard');

        $response->assertOk();
        $response->assertJsonPath('referrals_count', 1);
        $response->assertJsonPath('rewards_count', 1);
        $response->assertJsonPath('earned', 60);
        $response->assertJsonCount(1, 'redemptions');
    }

    public function test_the_dashboard_is_empty_for_a_client_with_no_activity(): void
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client->user);

        $response = $this->getJson('/api/clients/dashboard');

        $response->assertOk();
        $response->assertJsonPath('referrals_count', 0);
        $response->assertJsonPath('rewards_count', 0);
        $response->assertJsonPath('earned', 0);
    }
}
