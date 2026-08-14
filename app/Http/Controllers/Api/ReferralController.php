<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReferralStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Redemption;
use App\Models\Referral;
use App\Models\Reward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        $referral->load('salon.user');
        $referral->salon->user->notifications()->create([
            'type' => 'referral_redeemed',
            'payload' => [
                'referral_id' => $referral->id,
                'referrer_name' => $referrer->user->name,
                'referred_name' => $client->user->name,
            ],
        ]);

        return response()->json($referral, 201);
    }

    /**
     * The salon marks a pending referral complete once the referred
     * client actually shows up and gets serviced, picking which of the
     * salon's active rewards to pay out. This is what turns a redemption
     * event into something both the salon's and the clients' dashboards
     * actually show.
     */
    public function complete(Request $request, Referral $referral): JsonResponse
    {
        $salon = $request->user()->salon;

        if (! $salon || $referral->salon_id !== $salon->id) {
            abort(403, 'This referral does not belong to your business.');
        }

        if ($referral->status === ReferralStatus::Redeemed) {
            throw ValidationException::withMessages([
                'referral' => 'This referral has already been completed.',
            ]);
        }

        $data = $request->validate([
            'reward_id' => [
                'required',
                'uuid',
                Rule::exists('rewards', 'id')
                    ->where('salon_id', $salon->id)
                    ->where('is_active', true),
            ],
        ]);

        $redemption = Redemption::create([
            'referral_id' => $referral->id,
            'reward_id' => $data['reward_id'],
            'redeemed_at' => now(),
        ]);

        $referral->update(['status' => ReferralStatus::Redeemed]);

        $reward = Reward::findOrFail($data['reward_id']);
        $referral->load('referrer.user', 'referred.user');
        foreach ([$referral->referrer->user, $referral->referred->user] as $recipient) {
            $recipient->notifications()->create([
                'type' => 'reward_earned',
                'payload' => [
                    'referral_id' => $referral->id,
                    'reward_description' => $reward->description,
                    'reward_value' => (float) $reward->reward_value,
                    'salon_name' => $salon->business_name,
                ],
            ]);
        }

        return response()->json($redemption->load('reward', 'referral'));
    }
}
