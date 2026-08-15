<?php

namespace App\Http\Controllers\Api;

use App\Enums\PlanType;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    /**
     * Step 2 of salon onboarding: pick a plan and start a trial. No billing
     * integration exists yet, so this just records the plan choice and a
     * 14-day trial window rather than charging anything.
     */
    public function store(Request $request): JsonResponse
    {
        $salon = $request->user()->salon;

        if (! $salon) {
            throw ValidationException::withMessages([
                'salon' => 'Complete your business profile first.',
            ]);
        }

        if ($salon->subscriptions()->exists()) {
            return response()->json([
                'message' => 'A subscription already exists for this business.',
            ], 409);
        }

        $data = $request->validate([
            'plan_type' => ['required', Rule::enum(PlanType::class)],
        ]);

        $subscription = $salon->subscriptions()->create([
            'plan_type' => $data['plan_type'],
            'status' => SubscriptionStatus::Trialing,
            'current_period_end' => now()->addDays(14),
        ]);

        return response()->json($subscription, 201);
    }

    /**
     * A salon cancels their own subscription. Doesn't revoke dashboard
     * access on the spot — there's no billing period tracking to know
     * whether they're still inside a paid-for window — it just records
     * that they've cancelled, same as it would in a real billing system.
     */
    public function destroy(Request $request): JsonResponse
    {
        $salon = $request->user()->salon;

        // Query fresh rather than the cached `subscription` relation — this
        // controller and store() are sometimes exercised against the same
        // resolved user within a single request lifecycle (e.g. tests using
        // Sanctum::actingAs), where a relation loaded before a write can
        // return stale data.
        $subscription = $salon
            ? $salon->subscriptions()->latest('created_at')->first()
            : null;

        if (! $subscription) {
            throw ValidationException::withMessages([
                'subscription' => 'There is no subscription to cancel.',
            ]);
        }

        if ($subscription->status === SubscriptionStatus::Cancelled) {
            throw ValidationException::withMessages([
                'subscription' => 'This subscription is already cancelled.',
            ]);
        }

        $subscription->update(['status' => SubscriptionStatus::Cancelled]);

        return response()->json($subscription);
    }
}
