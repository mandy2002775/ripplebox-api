<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OtpAuthController extends Controller
{
    public function __construct(private SmsService $sms)
    {
    }

    /**
     * Generate and send a one-time code to a phone number.
     */
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
        ]);

        // Invalidate any codes still outstanding for this number so only the
        // latest one is ever valid.
        OtpCode::where('phone_number', $data['phone_number'])
            ->whereNull('consumed_at')
            ->delete();

        $isAppReviewNumber = filled(config('services.app_review.phone_number'))
            && $data['phone_number'] === config('services.app_review.phone_number');

        $code = $isAppReviewNumber
            ? config('services.app_review.code')
            : (string) random_int(100000, 999999);

        OtpCode::create([
            'phone_number' => $data['phone_number'],
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        // App/Play Store reviewers can't receive a real text, so the one
        // designated review number never goes through SMS at all — its
        // code is fixed and handed to the reviewer directly instead.
        if (! $isAppReviewNumber) {
            try {
                $this->sms->send($data['phone_number'], "Your Ripplebox code is {$code}. It expires in 10 minutes.");
            } catch (RuntimeException) {
                // The OTP row above is already invalidated-then-recreated, so a
                // failed send here can't be silently reported as success — the
                // user would be stuck with a code that never arrived.
                throw ValidationException::withMessages([
                    'phone_number' => "Couldn't send a code to that number right now. Please try again shortly.",
                ]);
            }
        }

        return response()->json([
            'message' => 'Code sent.',
            'debug_code' => (app()->environment('local') && ! $this->sms->isConfigured()) ? $code : null,
        ]);
    }

    /**
     * Verify a code and issue a Sanctum bearer token, registering the user
     * on first verification.
     */
    public function verify(Request $request): JsonResponse
    {
        $existingUser = User::where('phone_number', $request->input('phone_number'))->first();

        $data = $request->validate([
            'phone_number' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
            'name' => [Rule::requiredIf(! $existingUser), 'string', 'max:255'],
            'user_type' => [Rule::requiredIf(! $existingUser), Rule::enum(UserType::class)],
        ]);

        $otp = OtpCode::where('phone_number', $data['phone_number'])
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $otp || ! Hash::check($data['code'], $otp->code)) {
            throw ValidationException::withMessages([
                'code' => 'That code is invalid or has expired.',
            ]);
        }

        $otp->update(['consumed_at' => now()]);

        $user = $existingUser ?? User::create([
            'phone_number' => $data['phone_number'],
            'name' => $data['name'],
            'user_type' => $data['user_type'],
        ]);

        if (! $existingUser && $user->user_type === UserType::Client) {
            Client::create([
                'user_id' => $user->id,
                'referral_code' => $this->generateReferralCode($user->name),
            ]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load(['client', 'salon.subscription']),
        ], $existingUser ? 200 : 201);
    }

    /**
     * Revoke the bearer token used to make this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    private function generateReferralCode(string $name): string
    {
        $prefix = Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', $name) ?: 'USER', 0, 4));

        do {
            $code = $prefix.random_int(10, 99);
        } while (Client::where('referral_code', $code)->exists());

        return $code;
    }
}
