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
}
