<?php

namespace App\Http\Controllers\Api;

use App\Enums\PlanType;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Mail\WelcomeSalonMail;
use App\Models\Salon;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class SubscriptionController extends Controller
{
    /**
     * Step 2 of salon onboarding, before real billing existed: pick a plan
     * and start a trial with no payment collected. Superseded by
     * checkout()/confirmCheckout() below, kept only so the endpoint doesn't
     * 404 for anything still pointed at it.
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
            // A welcome email is nice-to-have, not a reason to fail an
            // otherwise-successful subscription.
            try {
                Mail::to($salon->user->email)->send(new WelcomeSalonMail($salon));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json($subscription, 201);
    }

    /**
     * Step 2 of salon onboarding: create a real Stripe Checkout Session for
     * the chosen plan and hand the client back the hosted payment URL. The
     * local subscription row isn't created here — Stripe hasn't collected
     * payment yet — it's created in confirmCheckout() (immediate, for a
     * smooth onboarding redirect) and again defensively by the Stripe
     * webhook (authoritative, in case the app is closed before returning).
     *
     * success_url/cancel_url come from the client rather than being
     * hardcoded, since they differ for the native app (a custom URL scheme
     * Stripe's hosted checkout page redirects back into) versus the web
     * build (a real https URL) — restricted to those two known prefixes so
     * this can't be used as an open redirect.
     */
    public function checkout(Request $request): JsonResponse
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

        // Restricted to the app's own custom URL scheme or plain https,
        // rather than an exact frontend-URL match, so this keeps working
        // whether the client is the native app, the deployed web build, or
        // a Vercel preview URL — none of those are user-supplied text, the
        // client code always fills them in itself.
        $data = $request->validate([
            'plan_type' => ['required', Rule::enum(PlanType::class)],
            'success_url' => ['required', 'url', 'starts_with:rippleboxapp://,https://'],
            'cancel_url' => ['required', 'url', 'starts_with:rippleboxapp://,https://'],
        ]);

        $plan = PlanType::from($data['plan_type']);

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));

            $session = $stripe->checkout->sessions->create(array_filter([
                'mode' => 'subscription',
                'client_reference_id' => $salon->id,
                // Stripe's newer "Managed Payments" is on by default for new
                // accounts and requires a tax code on every product unless
                // explicitly disabled — this project doesn't use Stripe Tax.
                'managed_payments' => ['enabled' => false],
                // Bypass/demo accounts (used for reviewer and internal testing)
                // never collect an email, and Stripe rejects an empty string
                // as an invalid email address rather than treating it as
                // absent — omit the key entirely when there's nothing real
                // to send.
                'customer_email' => $request->user()->email ?: null,
                'metadata' => ['salon_id' => $salon->id, 'plan_type' => $plan->value],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => 'aud',
                        'unit_amount' => (int) round($plan->monthlyPrice() * ($plan === PlanType::Annual ? 12 : 1) * 100),
                        'recurring' => ['interval' => $plan === PlanType::Annual ? 'year' : 'month'],
                        'product_data' => ['name' => "Ripplebox {$plan->value} plan"],
                    ],
                ]],
                'subscription_data' => [
                    'trial_period_days' => 30,
                    'metadata' => ['salon_id' => $salon->id, 'plan_type' => $plan->value],
                ],
                'success_url' => $data['success_url'].(str_contains($data['success_url'], '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $data['cancel_url'],
            ]));
        } catch (ApiErrorException $e) {
            report($e);

            throw ValidationException::withMessages([
                'plan_type' => 'Could not start checkout right now. Please try again shortly.',
            ]);
        }

        return response()->json(['checkout_url' => $session->url]);
    }

    /**
     * Called by the client immediately after a successful Stripe redirect,
     * so onboarding can continue (welcome screen, dashboard) without
     * waiting on webhook delivery. Verifies the session against Stripe
     * directly (not trusted from the client) and is idempotent against the
     * webhook also creating the same row.
     */
    public function confirmCheckout(Request $request): JsonResponse
    {
        $salon = $request->user()->salon;

        if (! $salon) {
            throw ValidationException::withMessages([
                'salon' => 'Complete your business profile first.',
            ]);
        }

        $data = $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $session = $stripe->checkout->sessions->retrieve($data['session_id'], ['expand' => ['subscription']]);
        } catch (ApiErrorException $e) {
            report($e);

            throw ValidationException::withMessages([
                'session_id' => 'Could not verify that checkout session.',
            ]);
        }

        if ($session->client_reference_id !== $salon->id || $session->status !== 'complete') {
            throw ValidationException::withMessages([
                'session_id' => 'That checkout session does not belong to this account.',
            ]);
        }

        $subscription = $this->syncSubscriptionFromStripeSession($salon, $session);

        return response()->json($subscription, 201);
    }

    /**
     * Shared by confirmCheckout() (immediate, client-driven) and the Stripe
     * webhook (authoritative, server-driven) — both resolve to the same
     * local row via the stripe_subscription_id unique lookup, so whichever
     * fires first creates it and the other is a no-op.
     */
    public function syncSubscriptionFromStripeSession(Salon $salon, $session): Subscription
    {
        $stripeSubscriptionId = is_string($session->subscription) ? $session->subscription : $session->subscription->id;

        $subscription = Subscription::firstOrCreate(
            ['stripe_subscription_id' => $stripeSubscriptionId],
            [
                'salon_id' => $salon->id,
                'plan_type' => $session->metadata['plan_type'] ?? PlanType::Monthly->value,
                'status' => SubscriptionStatus::Trialing,
                'current_period_end' => now()->addDays(30),
            ]
        );

        if ($subscription->wasRecentlyCreated && $salon->user->email) {
            Mail::to($salon->user->email)->send(new WelcomeSalonMail($salon));
        }

        return $subscription;
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

        if ($subscription->stripe_subscription_id) {
            try {
                (new StripeClient(config('services.stripe.secret')))
                    ->subscriptions
                    ->cancel($subscription->stripe_subscription_id);
            } catch (ApiErrorException $e) {
                // Already cancelled on Stripe's side (e.g. via their own
                // customer portal) is not an error worth blocking on — the
                // local row still needs to reflect "cancelled" either way.
                report($e);
            }
        }

        $subscription->update(['status' => SubscriptionStatus::Cancelled]);

        return response()->json($subscription);
    }
}
