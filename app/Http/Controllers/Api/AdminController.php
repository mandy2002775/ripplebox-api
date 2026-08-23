<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Referral;
use App\Models\Salon;
use App\Models\SalonLead;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    /**
     * Platform-wide metrics for screen 9 (Admin panel). "Monthly revenue"
     * is an estimate — sum of PlanType::monthlyPrice() over every
     * non-cancelled subscription — there's no real Stripe billing to read
     * an actual collected amount from, so this counts trialing salons
     * toward revenue too, same as the prototype's own example does.
     */
    public function stats(): JsonResponse
    {
        $weekAgo = now()->subWeek();

        $revenue = Subscription::where('status', '!=', SubscriptionStatus::Cancelled)
            ->get()
            ->sum(fn (Subscription $s) => $s->plan_type->monthlyPrice());

        return response()->json([
            'active_salons_count' => Salon::count(),
            'active_salons_this_week' => Salon::where('created_at', '>=', $weekAgo)->count(),
            'total_clients_count' => Client::count(),
            'total_clients_this_week' => Client::where('created_at', '>=', $weekAgo)->count(),
            'total_referrals_count' => Referral::count(),
            'total_referrals_this_week' => Referral::where('created_at', '>=', $weekAgo)->count(),
            'monthly_revenue' => round($revenue, 2),
            'pending_leads_count' => SalonLead::count(),
        ]);
    }

    /**
     * Salon signups captured via the website's webhook (FR-15), for the
     * admin team to follow up with — newest first.
     */
    public function leads(): JsonResponse
    {
        return response()->json(SalonLead::orderByDesc('created_at')->limit(50)->get());
    }

    /**
     * Recent subscriptions across every salon, newest first.
     */
    public function subscriptions(): JsonResponse
    {
        $subscriptions = Subscription::with('salon')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Subscription $s) => [
                'id' => $s->id,
                'salon_name' => $s->salon->business_name,
                'plan_type' => $s->plan_type,
                'status' => $s->status,
                'current_period_end' => $s->current_period_end,
            ]);

        return response()->json($subscriptions);
    }
}
