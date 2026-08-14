<?php

namespace Tests\Feature;

use App\Models\Salon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_salon_can_start_a_trial_subscription(): void
    {
        $salon = Salon::factory()->create();
        Sanctum::actingAs($salon->user);

        $response = $this->postJson('/api/salons/subscription', [
            'plan_type' => 'monthly',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('subscriptions', [
            'salon_id' => $salon->id,
            'plan_type' => 'monthly',
            'status' => 'trialing',
        ]);

        $periodEnd = $response->json('current_period_end');
        $this->assertNotNull($periodEnd);
        $this->assertEqualsWithDelta(
            now()->addDays(14)->timestamp,
            \Illuminate\Support\Carbon::parse($periodEnd)->timestamp,
            5
        );
    }

    public function test_a_salon_cannot_start_a_second_subscription(): void
    {
        $salon = Salon::factory()->create();
        Sanctum::actingAs($salon->user);

        $this->postJson('/api/salons/subscription', ['plan_type' => 'monthly'])
            ->assertCreated();

        $response = $this->postJson('/api/salons/subscription', ['plan_type' => 'annual']);

        $response->assertStatus(409);
    }

    public function test_me_includes_the_subscription_once_started(): void
    {
        $salon = Salon::factory()->create();
        Sanctum::actingAs($salon->user);

        $this->postJson('/api/salons/subscription', ['plan_type' => 'annual'])
            ->assertCreated();

        $response = $this->getJson('/api/me');

        $response->assertOk();
        $response->assertJsonPath('salon.subscription.plan_type', 'annual');
    }
}
