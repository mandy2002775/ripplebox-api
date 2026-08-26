<?php

namespace App\Http\Controllers\Api;

use App\Enums\PlanType;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Mail\WelcomeSalonMail;
use App\Models\Salon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    /**
     * Step 2 of salon onboarding: pick a plan and start a trial. No billing
     * integration exists yet, so this just records the plan choice and a
     * 30-day trial window rather than charging anything.
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

        // Subscriptions can legitimately have more than one row per salon
        // over time (cancel, then re-subscribe later), so this can't be a
        // DB unique constraint the way salons/redemptions/referrals are —
        // instead, locking the salon row itself serializes two concurrent
        // "start a subscription" submits so the second sees the first's
        // row and is rejected cleanly rather than creating a duplicate
        // trial.
        $subscription = DB::transaction(function () use ($salon, $data) {
            $locked = Salon::whereKey($salon->id)->lockForUpdate()->firstOrFail();

            if ($locked->subscriptions()->exists()) {
                return null;
            }

            return $locked->subscriptions()->create([
                'plan_type' => $data['plan_type'],
                'status' => SubscriptionStatus::Trialing,
                'current_period_end' => now()->addDays(30),
            ]);
        });

        if (! $subscription) {
            return response()->json([
                'message' => 'A subscription already exists for this business.',
            ], 409);
        }

        if ($salon->user->email) {
            Mail::to($salon->user->email)->send(new WelcomeSalonMail($salon));
        }

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
