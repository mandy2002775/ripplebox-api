<?php

namespace App\Http\Controllers\Api;

use App\Enums\PlanType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Mail\WelcomeSalonMail;
use App\Models\SalonLead;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * FR-15 (HIGHEST PRIORITY per the assessment report): "A salon subscribing
 * via the website must automatically trigger database account creation,
 * subscription record, and a formatted welcome email with working links."
 * The prior build only sent a broken, linkless email and never actually
 * created the account — this creates the real User/Salon/Subscription rows
 * and sends a real WelcomeSalonMail synchronously, so there's no gap
 * between "subscribed on the website" and "account exists in the app."
 *
 * The account is created without a password (FR-01 is phone-OTP only) — the
 * salon owner's first /auth/otp/verify against this phone number logs them
 * straight into the already-provisioned account, since that endpoint
 * already treats a matching existing user as a login rather than a
 * registration.
 */
class WebhookController extends Controller
{
    public function salonSignup(Request $request): JsonResponse
    {
        $secret = config('services.website_webhook.secret');

        if (! $secret || ! hash_equals($secret, (string) $request->header('X-Webhook-Secret'))) {
            abort(401, 'Invalid webhook secret.');
        }

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/', Rule::unique('users', 'phone_number')],
            'email' => ['nullable', 'email', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'plan_type' => ['nullable', Rule::enum(PlanType::class)],
        ]);

        $planType = $data['plan_type'] ?? PlanType::Monthly->value;

        $salon = DB::transaction(function () use ($data, $planType) {
            $user = User::create([
                'phone_number' => $data['phone_number'],
                'name' => $data['owner_name'],
                'email' => $data['email'] ?? null,
                'user_type' => UserType::Salon,
            ]);

            $salon = Salon::create([
                'user_id' => $user->id,
                'business_name' => $data['business_name'],
                'location' => $data['location'],
                'subscription_status' => SubscriptionStatus::Trialing,
            ]);

            $salon->subscriptions()->create([
                'plan_type' => $planType,
                'status' => SubscriptionStatus::Trialing,
                'current_period_end' => now()->addDays(30),
            ]);

            SalonLead::create([
                'business_name' => $data['business_name'],
                'phone_number' => $data['phone_number'],
                'email' => $data['email'] ?? null,
                'location' => $data['location'],
                'source' => 'website',
            ]);

            return $salon;
        });

        if ($data['email'] ?? null) {
            Mail::to($data['email'])->send(new WelcomeSalonMail($salon));
        }

        return response()->json($salon->load('subscription'), 201);
    }
}
