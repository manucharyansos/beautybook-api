<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send SMS/WhatsApp code. Default driver is log (safe for dev).
     * For production you can wire Twilio/MessageBird/etc behind env flags.
     */
    public function send(string $toE164, string $message): void
    {
        $driver = config('services.sms.driver', 'log');

        if ($driver === 'log') {
            Log::info('[sms:log]', ['to' => $toE164, 'message' => $message]);
            return;
        }

        // Placeholder for real providers
        // if ($driver === 'twilio') { ... }

        Log::warning('[sms:unknown-driver]', ['driver' => $driver, 'to' => $toE164]);
    }
}
