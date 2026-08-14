<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReferralController extends Controller
{
    /**
     * A client redeems a friend's referral code at a specific salon. This
     * is the one mechanic the whole app is built around, and it isn't
     * represented as its own screen in the 11-screen prototype — the
     * closest fit is "Nearby salons" on screen 7, so redemption happens
     * from there: pick a salon, enter the code you were given.
     */
    public function store(Request $request): JsonResponse
    {
        $client = $request->user()->client;

        if (! $client) {
            throw ValidationException::withMessages([
                'client' => 'Only client accounts can redeem a referral code.',
            ]);
        }

        $data = $request->validate([
            'referral_code' => ['required', 'string'],
            'salon_id' => ['required', 'uuid', 'exists:salons,id'],
        ]);

        $referrer = Client::where('referral_code', $data['referral_code'])->first();

        if (! $referrer) {
            throw ValidationException::withMessages([
                'referral_code' => 'That referral code doesn\'t exist.',
            ]);
        }

        if ($referrer->id === $client->id) {
            throw ValidationException::withMessages([
                'referral_code' => 'You can\'t redeem your own referral code.',
            ]);
        }

        $alreadyExists = Referral::where('referrer_client_id', $referrer->id)
            ->where('referred_client_id', $client->id)
            ->where('salon_id', $data['salon_id'])
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'message' => 'You\'ve already redeemed this code at this salon.',
            ], 409);
        }

        $referral = Referral::create([
            'referrer_client_id' => $referrer->id,
            'referred_client_id' => $client->id,
            'salon_id' => $data['salon_id'],
            'status' => 'pending',
        ]);

        return response()->json($referral->load('salon'), 201);
    }
}
