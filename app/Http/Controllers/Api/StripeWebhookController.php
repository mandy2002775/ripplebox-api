<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Authoritative subscription lifecycle sync — the client-driven
 * SubscriptionController::confirmCheckout() creates the row immediately for
 * a smooth onboarding redirect, but only Stripe's own webhook ever learns
 * about renewals, failed payments, or a cancellation made through Stripe's
 * customer portal rather than the app.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
            );
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            Log::warning("Rejected Stripe webhook: {$e->getMessage()}");

            abort(400, 'Invalid signature.');
        }

        match ($event->type) {
            'checkout.session.completed' => $this->onCheckoutCompleted($event->data->object),
            'customer.subscription.updated' => $this->onSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->onSubscriptionUpdated($event->data->object),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    private function onCheckoutCompleted(object $session): void
    {
        if ($session->mode !== 'subscription' || $session->status !== 'complete') {
            return;
        }

        $salon = \App\Models\Salon::find($session->client_reference_id);

        if (! $salon) {
            Log::error("Stripe checkout.session.completed for unknown salon: {$session->client_reference_id}");

            return;
        }

        app(SubscriptionController::class)->syncSubscriptionFromStripeSession($salon, $session);
    }

    private function onSubscriptionUpdated(object $stripeSubscription): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

        if (! $subscription) {
            // Created via confirmCheckout() instead, or genuinely unknown —
            // either way there's nothing local to update yet.
            return;
        }

        $status = match ($stripeSubscription->status) {
            'trialing' => SubscriptionStatus::Trialing,
            'active' => SubscriptionStatus::Active,
            'past_due', 'unpaid', 'incomplete' => SubscriptionStatus::Overdue,
            'canceled', 'incomplete_expired' => SubscriptionStatus::Cancelled,
            default => $subscription->status,
        };

        $subscription->update([
            'status' => $status,
            'current_period_end' => isset($stripeSubscription->current_period_end)
                ? now()->createFromTimestamp($stripeSubscription->current_period_end)
                : $subscription->current_period_end,
        ]);
    }
}
