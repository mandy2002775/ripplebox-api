<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

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
}
