<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReferralStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalonClientController extends Controller
{
    /**
     * Every client who's touched this salon through a referral, either as
     * the referrer or the one referred in — deduped, newest activity
     * first. There's no separate "salon customers" table; a client's
     * relationship to a salon only ever exists through its referrals.
     */
    public function index(Request $request): JsonResponse
    {
        $salon = $request->user()->salon;

        if (! $salon) {
            throw ValidationException::withMessages([
                'salon' => 'Complete your business profile first.',
            ]);
        }

        $referrals = $salon->referrals()->with(['referrer.user', 'referred.user'])->get();

        $byId = collect();
        foreach ($referrals as $r) {
            $byId->put($r->referrer_client_id, $r->referrer);
            $byId->put($r->referred_client_id, $r->referred);
        }

        $clients = $byId->filter()->map(function (Client $client) use ($referrals) {
            $made = $referrals->where('referrer_client_id', $client->id);
            $received = $referrals->where('referred_client_id', $client->id);
            $lastActivity = $referrals
                ->filter(fn ($r) => $r->referrer_client_id === $client->id || $r->referred_client_id === $client->id)
                ->max('created_at');

            return [
                'id' => $client->id,
                'name' => $client->user->name,
                'referrals_made' => $made->count(),
                'is_customer' => $received->contains('status', ReferralStatus::Redeemed),
                'last_activity' => $lastActivity,
            ];
        })->sortByDesc('last_activity')->values();

        return response()->json($clients);
    }
}
