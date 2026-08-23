<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.website_webhook.secret' => 'test-secret']);
    }

    public function test_a_correctly_signed_signup_creates_a_lead(): void
    {
        $response = $this->withHeader('X-Webhook-Secret', 'test-secret')
            ->postJson('/api/webhooks/salon-signup', [
                'business_name' => 'Gloss Hair Studio',
                'phone_number' => '+61412345678',
                'email' => 'owner@example.com',
                'location' => 'Perth WA',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('salon_leads', [
            'business_name' => 'Gloss Hair Studio',
            'source' => 'website',
        ]);
    }

    public function test_a_signup_without_the_correct_secret_is_rejected(): void
    {
        $response = $this->withHeader('X-Webhook-Secret', 'wrong-secret')
            ->postJson('/api/webhooks/salon-signup', ['business_name' => 'Gloss Hair Studio']);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('salon_leads', 0);
    }

    public function test_a_signup_missing_the_secret_header_is_rejected(): void
    {
        $response = $this->postJson('/api/webhooks/salon-signup', ['business_name' => 'Gloss Hair Studio']);

        $response->assertUnauthorized();
    }

    public function test_business_name_is_required(): void
    {
        $response = $this->withHeader('X-Webhook-Secret', 'test-secret')
            ->postJson('/api/webhooks/salon-signup', []);

        $response->assertUnprocessable();
    }
}
