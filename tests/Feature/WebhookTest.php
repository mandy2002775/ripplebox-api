<?php

namespace Tests\Feature;

use App\Mail\WelcomeSalonMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.website_webhook.secret' => 'test-secret']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Gloss Hair Studio',
            'owner_name' => 'Jamie Rivers',
            'phone_number' => '+61412345678',
            'email' => 'owner@example.com',
            'location' => 'Perth WA',
        ], $overrides);
    }

    public function test_a_correctly_signed_signup_creates_a_real_account_and_subscription(): void
    {
        $response = $this->withHeader('X-Webhook-Secret', 'test-secret')
            ->postJson('/api/webhooks/salon-signup', $this->payload());

        $response->assertCreated();
        $response->assertJsonPath('business_name', 'Gloss Hair Studio');
        $response->assertJsonPath('subscription.status', 'trialing');

        $this->assertDatabaseHas('users', [
            'phone_number' => '+61412345678',
            'name' => 'Jamie Rivers',
            'user_type' => 'salon',
        ]);
        $this->assertDatabaseHas('salons', ['business_name' => 'Gloss Hair Studio']);
        $this->assertDatabaseHas('subscriptions', ['plan_type' => 'monthly', 'status' => 'trialing']);
        $this->assertDatabaseHas('salon_leads', ['business_name' => 'Gloss Hair Studio', 'source' => 'website']);
    }

    public function test_a_signup_sends_the_welcome_email_when_an_email_is_given(): void
    {
        Mail::fake();

        $this->withHeader('X-Webhook-Secret', 'test-secret')
            ->postJson('/api/webhooks/salon-signup', $this->payload())
            ->assertCreated();

        Mail::assertSent(WelcomeSalonMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));
    }

    public function test_the_account_created_by_the_webhook_can_log_in_via_otp(): void
    {
        $this->withHeader('X-Webhook-Secret', 'test-secret')
            ->postJson('/api/webhooks/salon-signup', $this->payload())
            ->assertCreated();

        OtpCode::create([
            'phone_number' => '+61412345678',
            'code' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone_number' => '+61412345678',
            'code' => '123456',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.name', 'Jamie Rivers');
        $response->assertJsonPath('user.salon.business_name', 'Gloss Hair Studio');
    }

    public function test_a_signup_without_the_correct_secret_is_rejected(): void
    {
        $response = $this->withHeader('X-Webhook-Secret', 'wrong-secret')
            ->postJson('/api/webhooks/salon-signup', $this->payload());

        $response->assertUnauthorized();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_signup_missing_the_secret_header_is_rejected(): void
    {
        $response = $this->postJson('/api/webhooks/salon-signup', $this->payload());

        $response->assertUnauthorized();
    }

    public function test_required_fields_are_validated(): void
    {
        $response = $this->withHeader('X-Webhook-Secret', 'test-secret')
            ->postJson('/api/webhooks/salon-signup', []);

        $response->assertUnprocessable();
    }

    public function test_a_phone_number_already_in_use_is_rejected(): void
    {
        User::factory()->create(['phone_number' => '+61412345678']);

        $response = $this->withHeader('X-Webhook-Secret', 'test-secret')
            ->postJson('/api/webhooks/salon-signup', $this->payload());

        $response->assertUnprocessable();
    }
}
