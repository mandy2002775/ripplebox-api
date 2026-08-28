<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Referral;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalonClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_salon_without_a_profile_cannot_view_clients(): void
    {
        $user = User::factory()->salon()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/salons/clients')->assertUnprocessable();
    }

    public function test_it_lists_distinct_clients_from_both_sides_of_a_referral(): void
    {
        $salon = Salon::factory()->create();
        $referrer = Client::factory()->create();
        $referred = Client::factory()->create();
        Referral::create([
            'referrer_client_id' => $referrer->id,
            'referred_client_id' => $referred->id,
            'salon_id' => $salon->id,
            'status' => 'redeemed',
        ]);

        Sanctum::actingAs($salon->user);
        $response = $this->getJson('/api/salons/clients');

        $response->assertOk();
        $response->assertJsonCount(2);
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($referrer->id));
        $this->assertTrue($ids->contains($referred->id));
    }

    public function test_a_client_appearing_in_multiple_referrals_is_only_listed_once(): void
    {
        $salon = Salon::factory()->create();
        $referrer = Client::factory()->create();
        Referral::create([
            'referrer_client_id' => $referrer->id,
            'referred_client_id' => Client::factory()->create()->id,
            'salon_id' => $salon->id,
            'status' => 'pending',
        ]);
        Referral::create([
            'referrer_client_id' => $referrer->id,
            'referred_client_id' => Client::factory()->create()->id,
            'salon_id' => $salon->id,
            'status' => 'redeemed',
        ]);

        Sanctum::actingAs($salon->user);
        $response = $this->getJson('/api/salons/clients');

        $referrerRow = collect($response->json())->firstWhere('id', $referrer->id);
        $this->assertSame(2, $referrerRow['referrals_made']);
    }

    public function test_is_customer_is_true_only_once_their_own_referral_is_redeemed(): void
    {
        $salon = Salon::factory()->create();
        $referred = Client::factory()->create();
        Referral::create([
            'referrer_client_id' => Client::factory()->create()->id,
            'referred_client_id' => $referred->id,
            'salon_id' => $salon->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($salon->user);
        $response = $this->getJson('/api/salons/clients');

        $referredRow = collect($response->json())->firstWhere('id', $referred->id);
        $this->assertFalse($referredRow['is_customer']);
    }

    public function test_a_referral_at_a_different_salon_is_not_included(): void
    {
        $salon = Salon::factory()->create();
        $otherSalon = Salon::factory()->create();
        Referral::create([
            'referrer_client_id' => Client::factory()->create()->id,
            'referred_client_id' => Client::factory()->create()->id,
            'salon_id' => $otherSalon->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($salon->user);
        $response = $this->getJson('/api/salons/clients');

        $response->assertOk();
        $response->assertJsonCount(0);
    }
}
