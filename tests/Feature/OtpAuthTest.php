<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\OtpCode;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.name', 'Jane Doe');
        $this->assertNotNull($response->json('token'));

        $user = User::where('phone_number', '+61411111222')->first();
        $this->assertNotNull($user->client);
        $this->assertNotNull($user->client->referral_code);
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
}
