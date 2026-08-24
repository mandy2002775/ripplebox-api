<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_signup_form_can_capture_a_lead_without_any_auth(): void
    {
        $response = $this->postJson('/api/leads', [
            'business_name' => 'Gloss Hair Studio',
            'owner_name' => 'Jamie Rivers',
            'phone_number' => '+61412345678',
            'email' => 'owner@example.com',
            'location' => 'Perth WA',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('salon_leads', [
            'business_name' => 'Gloss Hair Studio',
            'owner_name' => 'Jamie Rivers',
            'source' => 'website',
        ]);
    }

    public function test_it_does_not_create_a_real_account_unlike_the_secret_gated_webhook(): void
    {
        $this->postJson('/api/leads', [
            'business_name' => 'Gloss Hair Studio',
            'owner_name' => 'Jamie Rivers',
        ])->assertCreated();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('salons', 0);
    }

    public function test_business_name_and_owner_name_are_required(): void
    {
        $this->postJson('/api/leads', [])->assertUnprocessable();
    }

    public function test_phone_email_and_location_are_optional(): void
    {
        $response = $this->postJson('/api/leads', [
            'business_name' => 'Gloss Hair Studio',
            'owner_name' => 'Jamie Rivers',
        ]);

        $response->assertCreated();
    }
}
