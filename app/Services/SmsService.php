<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client as TwilioClient;

/**
 * Real OTP delivery (FR-01) via Twilio Verify's "custom code" mode — we
 * still generate and hash the code ourselves (see OtpAuthController), Verify
 * is only used as the delivery channel. This deliberately avoids Twilio's
 * A2P 10DLC brand/campaign registration, which applies to raw
 * Messaging-Service SMS sends, not to Verify. Falls back to logging the
 * message when Twilio isn't configured (local/dev, or before the client's
 * Twilio account exists), so nothing here needs an environment check at the
 * call site — OtpAuthController::request() just calls send() unconditionally.
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
    public function send(string $toPhoneNumber, string $code): void
    {
        if (! $this->isConfigured()) {
            Log::info("[SMS not configured — logging only] To {$toPhoneNumber}: code {$code}");

            return;
        }

        try {
            $client = new TwilioClient(config('services.twilio.sid'), config('services.twilio.token'));

            $client->verify->v2->services(config('services.twilio.verify_sid'))
                ->verifications
                ->create($toPhoneNumber, 'sms', ['customCode' => $code]);
        } catch (TwilioException $e) {
            Log::error("Failed to send SMS to {$toPhoneNumber}: {$e->getMessage()}");

            throw new RuntimeException('Could not send the SMS.', previous: $e);
        }
    }
}
