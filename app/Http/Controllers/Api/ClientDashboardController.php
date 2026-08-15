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

        $referralsMade = $client->referralsMade()
            ->with(['referred.user', 'salon'])
            ->orderByDesc('created_at')
            ->get();

        $referralIds = $referralsMade->pluck('id')
            ->merge($client->referralsReceived()->pluck('id'));

        $redemptions = Redemption::whereIn('referral_id', $referralIds)
            ->with(['reward.salon', 'referral'])
            ->get();

        return response()->json([
            'referrals_count' => $referralsMade->count(),
            'rewards_count' => $redemptions->count(),
            'earned' => $redemptions->sum(fn ($r) => (float) $r->reward->reward_value),
            // Referrals this client sent, regardless of status — lets them see
            // ones still awaiting the salon to mark complete, not just the
            // ones that already turned into a redemption below.
            'referrals' => $referralsMade->map(fn ($r) => [
                'id' => $r->id,
                'referred_name' => $r->referred->user->name,
                'salon_name' => $r->salon->business_name,
                'status' => $r->status,
                'created_at' => $r->created_at,
            ]),
            'redemptions' => $redemptions->map(fn ($r) => [
                'id' => $r->id,
                'description' => $r->reward->description,
                'salon_name' => $r->reward->salon->business_name,
                'redeemed_at' => $r->redeemed_at,
            ]),
        ]);
    }
}
