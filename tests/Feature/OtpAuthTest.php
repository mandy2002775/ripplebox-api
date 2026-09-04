<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Mail\RegistrationMail;
use App\Models\OtpCode;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_code_creates_an_otp_row(): void
    {
        $response = $this->postJson('/api/auth/otp/request', [
            'phone_number' => '+61411111222',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('otp_codes', [
            'phone_number' => '+61411111222',
        ]);
    }

    public function test_verifying_a_valid_code_registers_a_new_client_and_returns_a_token(): void
    {
        OtpCode::create([
            'phone_number' => '+61411111222',
            'code' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone_number' => '+61411111222',
            'code' => '123456',
            'name' => 'Jane Doe',
            'user_type' => 'client',
            'email' => 'jane@example.com',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.name', 'Jane Doe');
        $this->assertNotNull($response->json('token'));

        $user = User::where('phone_number', '+61411111222')->first();
        $this->assertNotNull($user->client);
        $this->assertNotNull($user->client->referral_code);
    }

    public function test_a_new_client_signup_requires_an_email(): void
    {
        OtpCode::create([
            'phone_number' => '+61411111222',
            'code' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone_number' => '+61411111222',
            'code' => '123456',
            'name' => 'Jane Doe',
            'user_type' => 'client',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_registering_with_an_email_sends_a_welcome_email_immediately(): void
    {
        Mail::fake();

        OtpCode::create([
            'phone_number' => '+61411111222',
            'code' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/auth/otp/verify', [
            'phone_number' => '+61411111222',
            'code' => '123456',
            'name' => 'Jane Doe',
            'user_type' => 'client',
            'email' => 'jane@example.com',
        ])->assertCreated();

        Mail::assertSent(RegistrationMail::class, fn ($mail) => $mail->hasTo('jane@example.com'));
    }

    public function test_a_taken_email_cannot_be_reused_at_signup(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        OtpCode::create([
            'phone_number' => '+61411111222',
            'code' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone_number' => '+61411111222',
            'code' => '123456',
            'name' => 'Jane Doe',
            'user_type' => 'client',
            'email' => 'taken@example.com',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_verifying_an_expired_code_is_rejected(): void
    {
        OtpCode::create([
            'phone_number' => '+61411111222',
            'code' => Hash::make('123456'),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone_number' => '+61411111222',
            'code' => '123456',
            'name' => 'Jane Doe',
            'user_type' => 'client',
        ]);

        $response->assertUnprocessable();
    }

    public function test_verifying_an_existing_user_does_not_require_name_or_user_type(): void
    {
        $user = User::factory()->create(['phone_number' => '+61411111222']);

        OtpCode::create([
            'phone_number' => '+61411111222',
            'code' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone_number' => '+61411111222',
            'code' => '654321',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.id', $user->id);
    }

    public function test_verifying_an_existing_salon_owner_includes_their_subscription(): void
    {
        $salon = Salon::factory()->create();
        $salon->subscriptions()->create([
            'plan_type' => 'monthly',
            'status' => 'trialing',
            'current_period_end' => now()->addDays(14),
        ]);

        OtpCode::create([
            'phone_number' => $salon->user->phone_number,
            'code' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone_number' => $salon->user->phone_number,
            'code' => '654321',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.salon.subscription.plan_type', 'monthly');
    }

    public function test_the_app_review_number_always_gets_the_fixed_configured_code(): void
    {
        config(['services.app_review.phone_number' => '+61400000199', 'services.app_review.code' => '681146']);

        $this->postJson('/api/auth/otp/request', ['phone_number' => '+61400000199'])->assertOk();

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check(
                '681146',
                \App\Models\OtpCode::where('phone_number', '+61400000199')->latest('id')->first()->code
            )
        );
    }

    public function test_the_app_review_number_can_verify_with_the_fixed_code(): void
    {
        config(['services.app_review.phone_number' => '+61400000199', 'services.app_review.code' => '681146']);

        $this->postJson('/api/auth/otp/request', ['phone_number' => '+61400000199'])->assertOk();

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone_number' => '+61400000199',
            'code' => '681146',
            'name' => 'App Reviewer',
            'user_type' => 'client',
        ]);

        $response->assertCreated();
    }

    public function test_a_normal_number_is_unaffected_by_the_app_review_config(): void
    {
        config(['services.app_review.phone_number' => '+61400000199', 'services.app_review.code' => '681146']);

        $this->postJson('/api/auth/otp/request', ['phone_number' => '+61411111222'])->assertOk();

        $this->assertFalse(
            \Illuminate\Support\Facades\Hash::check(
                '681146',
                \App\Models\OtpCode::where('phone_number', '+61411111222')->latest('id')->first()->code
            )
        );
    }

    public function test_the_demo_salon_number_also_gets_a_fixed_code_and_can_verify(): void
    {
        config(['services.demo_salon.phone_number' => '+61400000299', 'services.demo_salon.code' => '924817']);

        $this->postJson('/api/auth/otp/request', ['phone_number' => '+61400000299'])->assertOk();

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone_number' => '+61400000299',
            'code' => '924817',
            'name' => 'Demo Salon',
            'user_type' => 'salon',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.user_type', 'salon');
    }

    public function test_the_admin_demo_number_also_gets_a_fixed_code_and_can_verify(): void
    {
        config(['services.admin_demo.phone_number' => '+61400000001', 'services.admin_demo.code' => '046242']);

        $this->postJson('/api/auth/otp/request', ['phone_number' => '+61400000001'])->assertOk();

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone_number' => '+61400000001',
            'code' => '046242',
            'name' => 'Kate Dawes',
            'user_type' => 'admin',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.user_type', 'admin');
    }

    public function test_the_app_review_and_demo_salon_bypasses_are_independent(): void
    {
        config([
            'services.app_review.phone_number' => '+61400000199',
            'services.app_review.code' => '681146',
            'services.demo_salon.phone_number' => '+61400000299',
            'services.demo_salon.code' => '924817',
        ]);

        $this->postJson('/api/auth/otp/request', ['phone_number' => '+61400000299'])->assertOk();

        $this->assertFalse(
            \Illuminate\Support\Facades\Hash::check(
                '681146',
                \App\Models\OtpCode::where('phone_number', '+61400000299')->latest('id')->first()->code
            )
        );
    }
}
