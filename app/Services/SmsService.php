<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client as TwilioClient;

/**
 * Real OTP delivery (FR-01) via Twilio Verify. Twilio generates and stores
 * the code itself (our account isn't enabled for Verify's "custom code"
 * feature, which returns a 403 "Custom code not allowed"), so callers must
 * validate through check() rather than comparing a locally-stored hash. This
 * deliberately avoids Twilio's A2P 10DLC brand/campaign registration, which
 * applies to raw Messaging-Service SMS sends, not to Verify.
 */
class SmsService
{
    public function isConfigured(): bool
    {
        return filled(config('services.twilio.sid'))
            && filled(config('services.twilio.token'))
            && filled(config('services.twilio.verify_sid'));
    }

    /**
     * @throws RuntimeException if Twilio is configured but the send fails —
     * callers must not report success to the user in that case, since an
     * OTP code that was never delivered leaves them unable to sign in.
     */
    public function start(string $toPhoneNumber): void
    {
        try {
            $client = new TwilioClient(config('services.twilio.sid'), config('services.twilio.token'));

            $client->verify->v2->services(config('services.twilio.verify_sid'))
                ->verifications
                ->create($toPhoneNumber, 'sms');
        } catch (TwilioException $e) {
            Log::error("Failed to send SMS to {$toPhoneNumber}: {$e->getMessage()}");

            throw new RuntimeException('Could not send the SMS.', previous: $e);
        }
    }

    public function check(string $toPhoneNumber, string $code): bool
    {
        try {
            $client = new TwilioClient(config('services.twilio.sid'), config('services.twilio.token'));

            $result = $client->verify->v2->services(config('services.twilio.verify_sid'))
                ->verificationChecks
                ->create(['code' => $code, 'to' => $toPhoneNumber]);

            return $result->status === 'approved';
        } catch (TwilioException $e) {
            Log::error("Failed to check verification for {$toPhoneNumber}: {$e->getMessage()}");

            return false;
        }
    }
}
