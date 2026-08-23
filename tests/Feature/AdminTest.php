<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Client;
use App\Models\Referral;
use App\Models\Salon;
use App\Models\SalonLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_cannot_view_platform_stats(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/stats')->assertForbidden();
    }

    public function test_an_admin_can_view_platform_stats(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::Admin]);
        $salon = Salon::factory()->create();
        $referrer = Client::factory()->create();
        $referred = Client::factory()->create();
        Referral::create([
            'referrer_client_id' => $referrer->id,
            'referred_client_id' => $referred->id,
            'salon_id' => $salon->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/stats');

        $response->assertOk();
        $response->assertJsonPath('active_salons_count', 1);
        $response->assertJsonPath('total_clients_count', 2);
        $response->assertJsonPath('total_referrals_count', 1);
    }

    public function test_an_admin_can_view_recent_subscriptions(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::Admin]);
        $salon = Salon::factory()->create(['business_name' => 'Gloss Hair Studio']);
        $salon->subscriptions()->create([
            'plan_type' => 'monthly',
            'status' => 'trialing',
            'current_period_end' => now()->addDays(30),
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/subscriptions');

        $response->assertOk();
        $response->assertJsonFragment(['salon_name' => 'Gloss Hair Studio', 'plan_type' => 'monthly']);
    }

    public function test_an_admin_can_view_website_signup_leads(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::Admin]);
        SalonLead::create(['business_name' => 'New Salon Co', 'source' => 'website']);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/leads');

        $response->assertOk();
        $response->assertJsonFragment(['business_name' => 'New Salon Co']);
    }

    public function test_a_non_admin_cannot_view_website_signup_leads(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/leads')->assertForbidden();
    }
}
