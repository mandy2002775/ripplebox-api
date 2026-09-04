<?php

namespace Tests\Feature;

use App\Mail\RewardEarnedMail;
use App\Models\Client;
use App\Models\Reward;
use App\Models\Salon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_a_client_cannot_redeem_a_code_at_an_unclaimed_salon(): void
    {
        $referrer = Client::factory()->create();
        $client = Client::factory()->create();
        $salon = Salon::factory()->create(['user_id' => null, 'source' => 'osm_import']);

        Sanctum::actingAs($client->user);

        $response = $this->postJson('/api/referrals', [
            'referral_code' => $referrer->referral_code,
            'salon_id' => $salon->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('salon_id');
        $this->assertDatabaseMissing('referrals', ['salon_id' => $salon->id]);
    }

    public function test_a_client_can_see_a_pending_referral_they_sent_on_their_dashboard(): void
    {
        $referrer = Client::factory()->create();
        $client = Client::factory()->create();
        $salon = Salon::factory()->create();

        Sanctum::actingAs($client->user);
        $this->postJson('/api/referrals', [
            'referral_code' => $referrer->referral_code,
            'salon_id' => $salon->id,
        ])->assertCreated();

        Sanctum::actingAs($referrer->user);
        $dashboard = $this->getJson('/api/clients/dashboard')->assertOk();

        $dashboard->assertJsonCount(1, 'referrals');
        $dashboard->assertJsonPath('referrals.0.referred_name', $client->user->name);
        $dashboard->assertJsonPath('referrals.0.salon_name', $salon->business_name);
        $dashboard->assertJsonPath('referrals.0.status', 'pending');
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

    public function test_the_owning_salon_can_mark_a_pending_referral_as_engaged(): void
    {
        $referrer = Client::factory()->create();
        $client = Client::factory()->create();
        $salon = Salon::factory()->create();

        Sanctum::actingAs($client->user);
        $this->postJson('/api/referrals', [
            'referral_code' => $referrer->referral_code,
            'salon_id' => $salon->id,
        ])->assertCreated();

        $referral = $salon->referrals()->first();

        Sanctum::actingAs($salon->user);
        $response = $this->patchJson("/api/referrals/{$referral->id}/engage");

        $response->assertOk();
        $this->assertDatabaseHas('referrals', [
            'id' => $referral->id,
            'status' => 'engaged',
        ]);
    }

    public function test_an_engaged_referral_can_still_be_completed(): void
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
        $this->patchJson("/api/referrals/{$referral->id}/engage")->assertOk();

        $response = $this->patchJson("/api/referrals/{$referral->id}/complete", [
            'reward_id' => $reward->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('referrals', [
            'id' => $referral->id,
            'status' => 'redeemed',
        ]);
    }

    public function test_a_referral_cannot_be_engaged_twice(): void
    {
        $referrer = Client::factory()->create();
        $client = Client::factory()->create();
        $salon = Salon::factory()->create();

        Sanctum::actingAs($client->user);
        $this->postJson('/api/referrals', [
            'referral_code' => $referrer->referral_code,
            'salon_id' => $salon->id,
        ])->assertCreated();

        $referral = $salon->referrals()->first();

        Sanctum::actingAs($salon->user);
        $this->patchJson("/api/referrals/{$referral->id}/engage")->assertOk();

        $response = $this->patchJson("/api/referrals/{$referral->id}/engage");

        $response->assertUnprocessable();
    }

    public function test_a_different_salon_cannot_engage_someone_elses_referral(): void
    {
        $referrer = Client::factory()->create();
        $client = Client::factory()->create();
        $salon = Salon::factory()->create();
        $otherSalon = Salon::factory()->create();

        Sanctum::actingAs($client->user);
        $this->postJson('/api/referrals', [
            'referral_code' => $referrer->referral_code,
            'salon_id' => $salon->id,
        ])->assertCreated();

        $referral = $salon->referrals()->first();

        Sanctum::actingAs($otherSalon->user);
        $response = $this->patchJson("/api/referrals/{$referral->id}/engage");

        $response->assertForbidden();
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

    public function test_completing_a_referral_emails_recipients_with_an_email_on_file(): void
    {
        Mail::fake();
        $referrer = Client::factory()->create();
        $client = Client::factory()->create();
        $referrer->user->update(['email' => 'referrer@example.com']);
        $salon = Salon::factory()->create();
        $reward = Reward::factory()->for($salon)->create();

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

        Mail::assertSent(RewardEarnedMail::class, fn ($mail) => $mail->hasTo('referrer@example.com'));
        Mail::assertSent(RewardEarnedMail::class, 1);
    }

    public function test_a_salon_cannot_pay_out_an_expired_reward(): void
    {
        $referrer = Client::factory()->create();
        $client = Client::factory()->create();
        $salon = Salon::factory()->create();
        // is_active alone doesn't mean redeemable — a reward past its own
        // expiry_date shouldn't be payable even if nobody paused it.
        $reward = Reward::factory()->for($salon)->create(['expiry_date' => now()->subDay()]);

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

        $response->assertUnprocessable();
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
