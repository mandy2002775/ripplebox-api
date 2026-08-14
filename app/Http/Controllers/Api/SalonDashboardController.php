<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReferralStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalonDashboardController extends Controller
{
    /**
     * Summary for screen 6. Deliberately omits cost-per-lead — the
     * prototype shows it, but computing it needs a revenue/transaction
     * figure this schema doesn't track yet, so it's left out rather than
     * faked.
     */
    public function show(Request $request): JsonResponse
    {
        $salon = $request->user()->salon;

        if (! $salon) {
            throw ValidationException::withMessages([
                'salon' => 'Complete your business profile first.',
            ]);
        }

        $referrals = $salon->referrals()
            ->with(['referrer.user', 'referred.user'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'referrals_count' => $referrals->count(),
            'converted_count' => $referrals->where('status', ReferralStatus::Redeemed)->count(),
            'recent_referrals' => $referrals->take(10)->map(fn ($r) => [
                'id' => $r->id,
                'referrer_name' => $r->referrer->user->name,
                'referred_name' => $r->referred->user->name,
                'status' => $r->status,
                'created_at' => $r->created_at,
            ]),
            'active_rewards' => $salon->rewards()
                ->where('is_active', true)
                ->withCount('redemptions')
                ->get(),
        ]);
    }
}
