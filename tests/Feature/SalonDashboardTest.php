<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Referral;
use App\Models\Reward;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalonDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_salon_without_a_profile_cannot_view_the_dashboard(): void
    {
        $user = User::factory()->salon()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/salons/dashboard')->assertUnprocessable();
    }

    public function test_the_dashboard_summarises_referrals_and_active_rewards(): void
    {
        $salon = Salon::factory()->create();
        Reward::factory()->for($salon)->create(['is_active' => true]);
        $referrer = Client::factory()->create();
        $referred = Client::factory()->create();
        Referral::create([
            'referrer_client_id' => $referrer->id,
            'referred_client_id' => $referred->id,
            'salon_id' => $salon->id,
            'status' => 'redeemed',
        ]);

        Sanctum::actingAs($salon->user);
        $response = $this->getJson('/api/salons/dashboard');

        $response->assertOk();
        $response->assertJsonPath('referrals_count', 1);
        $response->assertJsonPath('converted_count', 1);
        $response->assertJsonCount(1, 'active_rewards');
    }

    public function test_the_dashboard_excludes_expired_rewards_from_active_rewards(): void
    {
        $salon = Salon::factory()->create();
        Reward::factory()->for($salon)->create(['is_active' => true, 'expiry_date' => now()->subDay()]);

        Sanctum::actingAs($salon->user);
        $response = $this->getJson('/api/salons/dashboard');

        $response->assertOk();
        $response->assertJsonCount(0, 'active_rewards');
    }
}
