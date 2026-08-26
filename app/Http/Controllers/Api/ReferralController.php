<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReferralStatus;
use App\Http\Controllers\Controller;
use App\Mail\RewardEarnedMail;
use App\Models\Client;
use App\Models\Redemption;
use App\Models\Referral;
use App\Models\Reward;
use App\Models\Salon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

        // The exists() check above and this create() aren't atomic — two
        // identical requests can both pass the check before either writes.
        // The DB-level unique constraint (referrals_unique_triple) is the
        // real guarantee; this catch turns the race loser's failure into
        // the same clean 409 instead of a raw 500.
        try {
            $referral = Referral::create([
                'referrer_client_id' => $referrer->id,
                'referred_client_id' => $client->id,
                'salon_id' => $data['salon_id'],
                'status' => 'pending',
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                return response()->json([
                    'message' => 'You\'ve already redeemed this code at this salon.',
                ], 409);
            }

            throw $e;
        }

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
        $salon = $this->salonOwning($request, $referral);

        if ($referral->status === ReferralStatus::Redeemed) {
            throw ValidationException::withMessages([
                'referral' => 'This referral has already been completed.',
            ]);
        }

        $data = $request->validate([
            'reward_id' => [
                'required',
                'uuid',
                // DatabaseRule::where($column, $value) is equality-only — an
                // inequality like expiry_date >= today needs the closure form.
                Rule::exists('rewards', 'id')
                    ->where('salon_id', $salon->id)
                    ->where('is_active', true)
                    ->where(fn ($query) => $query->where('expiry_date', '>=', now()->toDateString())),
            ],
        ]);

        // Two concurrent "complete" requests for the same referral would
        // otherwise both pass the status check above before either writes,
        // paying the reward out twice. Locking the row inside a transaction
        // serializes them — the second request re-checks status after
        // acquiring the lock and is rejected cleanly instead of double-paying.
        $redemption = DB::transaction(function () use ($referral, $data) {
            $locked = Referral::whereKey($referral->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ReferralStatus::Redeemed) {
                throw ValidationException::withMessages([
                    'referral' => 'This referral has already been completed.',
                ]);
            }

            $redemption = Redemption::create([
                'referral_id' => $locked->id,
                'reward_id' => $data['reward_id'],
                'redeemed_at' => now(),
            ]);

            $locked->update(['status' => ReferralStatus::Redeemed]);

            return $redemption;
        });

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

            if ($recipient->email) {
                Mail::to($recipient->email)->send(new RewardEarnedMail($recipient, $reward, $salon));
            }
        }

        return response()->json($redemption->load('reward', 'referral'));
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        // SQLSTATE 23000 covers integrity constraint violations across both
        // MySQL (production) and SQLite (dev/test).
        return $e->getCode() === '23000';
    }

    /**
     * The salon marks a pending referral as engaged once they've made
     * contact with (or booked in) the referred client, ahead of actually
     * completing the referral. Distinct from complete() — this doesn't pay
     * out a reward, it's just a lead-pipeline checkpoint between "redeemed
     * a code" and "showed up and got serviced".
     */
    public function engage(Request $request, Referral $referral): JsonResponse
    {
        $this->salonOwning($request, $referral);

        if ($referral->status !== ReferralStatus::Pending) {
            throw ValidationException::withMessages([
                'referral' => 'Only a pending referral can be marked as engaged.',
            ]);
        }

        $referral->update(['status' => ReferralStatus::Engaged]);

        return response()->json($referral);
    }

    private function salonOwning(Request $request, Referral $referral): Salon
    {
        $salon = $request->user()->salon;

        if (! $salon || $referral->salon_id !== $salon->id) {
            abort(403, 'This referral does not belong to your business.');
        }

        return $salon;
    }
}
