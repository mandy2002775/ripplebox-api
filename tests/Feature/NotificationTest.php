<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Reward;
use App\Models\Salon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_salon_is_notified_when_a_referral_is_redeemed(): void
    {
        $referrer = Client::factory()->create();
        $client = Client::factory()->create();
        $salon = Salon::factory()->create();

        Sanctum::actingAs($client->user);
        $this->postJson('/api/referrals', [
            'referral_code' => $referrer->referral_code,
            'salon_id' => $salon->id,
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $salon->user_id,
            'type' => 'referral_redeemed',
        ]);
    }

    public function test_both_clients_are_notified_when_a_referral_is_completed(): void
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
        $this->patchJson("/api/referrals/{$referral->id}/complete", [
            'reward_id' => $reward->id,
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $referrer->user_id,
            'type' => 'reward_earned',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->user_id,
            'type' => 'reward_earned',
        ]);
    }

    public function test_a_user_can_list_and_mark_their_notifications_read(): void
    {
        $client = Client::factory()->create();
        $client->user->notifications()->create([
            'type' => 'reward_earned',
            'payload' => ['reward_description' => 'Test reward'],
        ]);

        Sanctum::actingAs($client->user);

        $index = $this->getJson('/api/notifications')->assertOk();
        $index->assertJsonPath('unread_count', 1);
        $notificationId = $index->json('notifications.0.id');

        $this->patchJson("/api/notifications/{$notificationId}/read")->assertOk();

        $this->getJson('/api/notifications')->assertJsonPath('unread_count', 0);
    }

    public function test_a_user_cannot_mark_someone_elses_notification_read(): void
    {
        $owner = Client::factory()->create();
        $intruder = Client::factory()->create();
        $notification = $owner->user->notifications()->create([
            'type' => 'reward_earned',
            'payload' => [],
        ]);

        Sanctum::actingAs($intruder->user);

        $this->patchJson("/api/notifications/{$notification->id}/read")->assertForbidden();
    }
}
