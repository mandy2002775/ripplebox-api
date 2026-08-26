<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client as TwilioClient;

/**
 * Real OTP delivery (FR-01). Falls back to logging the message when Twilio
 * isn't configured (local/dev, or before the client's Twilio account
 * exists), so nothing here needs an environment check at the call site —
 * OtpAuthController::request() just calls send() unconditionally.
 */
class SmsService
{
    public function isConfigured(): bool
    {
        return filled(config('services.twilio.sid'))
            && filled(config('services.twilio.token'))
            && filled(config('services.twilio.from'));
    }

    /**
     * @throws RuntimeException if Twilio is configured but the send fails —
     * callers must not report success to the user in that case, since an
     * OTP code that was never delivered leaves them unable to sign in.
     */
    public function send(string $toPhoneNumber, string $message): void
    {
        if (! $this->isConfigured()) {
            Log::info("[SMS not configured — logging only] To {$toPhoneNumber}: {$message}");

            return;
        }

        try {
            $client = new TwilioClient(config('services.twilio.sid'), config('services.twilio.token'));

            $client->messages->create($toPhoneNumber, [
                'from' => config('services.twilio.from'),
                'body' => $message,
            ]);
        } catch (TwilioException $e) {
            Log::error("Failed to send SMS to {$toPhoneNumber}: {$e->getMessage()}");

            throw new RuntimeException('Could not send the SMS.', previous: $e);
        }
    }
}
