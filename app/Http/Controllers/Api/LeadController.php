<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalonLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The public "Start your free trial" form on the marketing website posts
 * here directly from the visitor's browser — no shared secret, since
 * anyone filling out a public form is, by definition, not a trusted
 * caller. This only ever captures a lead for the admin team to follow up
 * with; it deliberately does NOT create a real account (see
 * WebhookController::salonSignup for that — the secret-gated,
 * server-to-server version of this same "salon signed up on the website"
 * event, meant to be called by the website's own backend once it has
 * actually verified payment, not by the visitor's browser directly).
 */
class LeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        SalonLead::create([
            'business_name' => $data['business_name'],
            'owner_name' => $data['owner_name'],
            'phone_number' => $data['phone_number'] ?? null,
            'email' => $data['email'] ?? null,
            'location' => $data['location'] ?? null,
            'source' => 'website',
        ]);

        return response()->json([
            'message' => "Thanks! We'll be in touch to get you started.",
        ], 201);
    }
}
