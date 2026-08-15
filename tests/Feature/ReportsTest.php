<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Client;
use App\Models\Redemption;
use App\Models\Referral;
use App\Models\Reward;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function seedScenario(): array
    {
        $salon = Salon::factory()->create();
        $salon->subscriptions()->create([
            'plan_type' => 'monthly',
            'status' => 'trialing',
            'current_period_end' => now()->addDays(30),
        ]);
        $reward = Reward::factory()->for($salon)->create(['reward_value' => 50]);
        $referrer = Client::factory()->create();
        $referred = Client::factory()->create();
        $referral = Referral::create([
            'referrer_client_id' => $referrer->id,
            'referred_client_id' => $referred->id,
            'salon_id' => $salon->id,
            'status' => 'redeemed',
        ]);
        Redemption::create([
            'referral_id' => $referral->id,
            'reward_id' => $reward->id,
            'redeemed_at' => now(),
        ]);

        return compact('salon', 'referral');
    }

    public function test_a_non_admin_cannot_view_reports(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/reports')->assertForbidden();
    }

    public function test_an_admin_can_view_report_metrics_for_a_range(): void
    {
        $this->seedScenario();
        $admin = User::factory()->create(['user_type' => UserType::Admin]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/reports?range=7');

        $response->assertOk();
        $response->assertJsonPath('referrals_count', 1);
        $response->assertJsonPath('conversions_count', 1);
        $response->assertJsonStructure(['revenue', 'cost_per_lead_pct', 'daily', 'top_salons']);
    }

    public function test_csv_export_returns_a_csv_file(): void
    {
        $this->seedScenario();
        $admin = User::factory()->create(['user_type' => UserType::Admin]);
        Sanctum::actingAs($admin);

        $response = $this->get('/api/admin/reports/export.csv?range=all');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_pdf_export_returns_a_pdf_file(): void
    {
        $this->seedScenario();
        $admin = User::factory()->create(['user_type' => UserType::Admin]);
        Sanctum::actingAs($admin);

        $response = $this->get('/api/admin/reports/export.pdf?range=all');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
