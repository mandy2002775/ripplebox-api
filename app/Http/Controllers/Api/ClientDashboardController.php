<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Redemption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClientDashboardController extends Controller
{
    /**
     * Stats for screen 7 (Referrals / Rewards / Earned). "Rewards" and
     * "Earned" cover redemptions where this client was either side of
     * the referral — recipient_type isn't split out per-person here,
     * since a redemption is one event, not two.
     */
    public function show(Request $request): JsonResponse
    {
        $client = $request->user()->client;

        if (! $client) {
            throw ValidationException::withMessages([
                'client' => 'Client profile not found.',
            ]);
        }

        $referralIds = $client->referralsMade()->pluck('id')
            ->merge($client->referralsReceived()->pluck('id'));

        $redemptions = Redemption::whereIn('referral_id', $referralIds)
            ->with(['reward.salon', 'referral'])
            ->get();

        return response()->json([
            'referrals_count' => $client->referralsMade()->count(),
            'rewards_count' => $redemptions->count(),
            'earned' => $redemptions->sum(fn ($r) => (float) $r->reward->reward_value),
            'redemptions' => $redemptions->map(fn ($r) => [
                'id' => $r->id,
                'description' => $r->reward->description,
                'salon_name' => $r->reward->salon->business_name,
                'redeemed_at' => $r->redeemed_at,
            ]),
        ]);
    }
}
