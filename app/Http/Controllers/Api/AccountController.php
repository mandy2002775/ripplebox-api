<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * NFR-04 in-app privacy controls: an authenticated user can see everything
 * the app holds on them and remove it, without having to go through a
 * support request.
 */
class AccountController extends Controller
{
    /**
     * Auth is phone-only (FR-01), so email is optional and collected here —
     * the only thing it's used for is FR-10 transactional emails.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $hadNoEmailBefore = ! $user->email;

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        // FR-10's "registration" email has nowhere to fire at actual OTP
        // registration time — sign-up is phone-only and email is collected
        // later, opt-in, from profile settings. The honest equivalent here
        // is: the first time an email becomes attached to an already
        // OTP-verified account.
        if ($hadNoEmailBefore && $user->email) {
            Mail::to($user->email)->send(new RegistrationMail($user));
        }

        return response()->json($user->load(['client', 'salon.subscription']));
    }

    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'account' => $user->only(['id', 'name', 'phone_number', 'email', 'user_type', 'created_at']),
        ];

        if ($user->user_type === \App\Enums\UserType::Salon && $user->salon) {
            $salon = $user->salon->load([
                'rewards',
                'referrals.referrer.user',
                'referrals.referred.user',
                'subscriptions',
            ]);

            $data['salon'] = $salon->only([
                'id', 'business_name', 'location', 'website', 'instagram_handle',
                'logo_url', 'subscription_status', 'created_at',
            ]);
            $data['rewards'] = $salon->rewards;
            $data['referrals_received'] = $salon->referrals;
            $data['subscriptions'] = $salon->subscriptions;
        }

        if ($user->client) {
            $client = $user->client->load([
                'referralsMade.salon',
                'referralsMade.referred.user',
                'referralsReceived.salon',
                'referralsReceived.referrer.user',
            ]);

            $data['client'] = $client->only(['id', 'referral_code', 'created_at']);
            $data['referrals_made'] = $client->referralsMade;
            $data['referrals_received'] = $client->referralsReceived;
        }

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="ripplebox-my-data.json"');
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->tokens()->delete();
        $user->salon?->delete();
        $user->client?->delete();

        // phone_number has a hard DB-level unique constraint that a soft
        // delete doesn't relax — without this, the same number could never
        // sign up again (User::create() would hit the constraint and 500,
        // since the OTP lookup's SoftDeletes scope already can't see this
        // row to reuse it). Tombstoning it here is what actually makes
        // "deleted" mean deleted, rather than "permanently reserved."
        $user->update(['phone_number' => "{$user->phone_number}#deleted#{$user->id}"]);
        $user->delete();

        return response()->json(['message' => 'Your account and its data have been deleted.']);
    }
}
