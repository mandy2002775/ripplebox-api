<?php

namespace Tests\Feature;

use App\Mail\RegistrationMail;
use App\Models\Client;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_set_their_email(): void
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client->user);

        $response = $this->patchJson('/api/me', ['email' => 'me@example.com']);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $client->user->id, 'email' => 'me@example.com']);
    }

    public function test_setting_an_email_for_the_first_time_sends_a_registration_email(): void
    {
        Mail::fake();
        $client = Client::factory()->create();
        Sanctum::actingAs($client->user);

        $this->patchJson('/api/me', ['email' => 'me@example.com'])->assertOk();

        Mail::assertSent(RegistrationMail::class, fn ($mail) => $mail->hasTo('me@example.com'));
    }

    public function test_changing_an_already_set_email_does_not_resend_the_registration_email(): void
    {
        Mail::fake();
        $client = Client::factory()->create();
        $client->user->update(['email' => 'old@example.com']);
        Sanctum::actingAs($client->user);

        $this->patchJson('/api/me', ['email' => 'new@example.com'])->assertOk();

        Mail::assertNothingSent();
    }

    public function test_a_user_cannot_take_an_email_already_in_use(): void
    {
        $taken = User::factory()->create(['email' => 'taken@example.com']);
        $client = Client::factory()->create();
        Sanctum::actingAs($client->user);

        $response = $this->patchJson('/api/me', ['email' => 'taken@example.com']);

        $response->assertUnprocessable();
    }

    public function test_a_client_can_export_their_own_data(): void
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client->user);

        $response = $this->getJson('/api/me/export');

        $response->assertOk();
        $response->assertJsonPath('account.id', $client->user->id);
        $response->assertJsonPath('client.referral_code', $client->referral_code);
    }

    public function test_a_salon_can_export_their_own_data(): void
    {
        $salon = Salon::factory()->create();
        Sanctum::actingAs($salon->user);

        $response = $this->getJson('/api/me/export');

        $response->assertOk();
        $response->assertJsonPath('salon.business_name', $salon->business_name);
    }

    public function test_a_user_can_delete_their_own_account(): void
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client->user);

        $response = $this->deleteJson('/api/me');

        $response->assertOk();
        $this->assertSoftDeleted('users', ['id' => $client->user->id]);
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_deleting_an_account_revokes_its_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/me')
            ->assertOk();

        $this->assertEquals(0, PersonalAccessToken::where('tokenable_id', $user->id)->count());
    }

    public function test_the_same_phone_number_can_sign_up_fresh_after_the_account_is_deleted(): void
    {
        $user = User::factory()->create(['phone_number' => '+61411111222']);
        Sanctum::actingAs($user);
        $this->deleteJson('/api/me')->assertOk();

        $code = \App\Models\OtpCode::create([
            'phone_number' => '+61411111222',
            'code' => \Illuminate\Support\Facades\Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone_number' => '+61411111222',
            'code' => '123456',
            'name' => 'New Owner',
            'user_type' => 'client',
        ]);

        $response->assertCreated();
        $this->assertNotEquals($user->id, $response->json('user.id'));
        $this->assertDatabaseHas('users', ['id' => $response->json('user.id'), 'phone_number' => '+61411111222']);
    }
}
