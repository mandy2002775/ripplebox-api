<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalonLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-15: the marketing website's own salon-signup form posts here so a lead
 * lands in Ripplebox the moment someone signs up on the site, instead of
 * only existing in the website's own database. This captures the lead for
 * the admin team to follow up with — it deliberately doesn't auto-create a
 * User/Salon account, since real account creation still goes through
 * phone-OTP verification (FR-01), which the website can't do on someone
 * else's behalf.
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
            'phone_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $lead = SalonLead::create([...$data, 'source' => 'website']);

        return response()->json($lead, 201);
    }
}
