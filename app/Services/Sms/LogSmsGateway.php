<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Writes messages to the application log. Use it on a developer machine so no
 * real text message is sent and no credits are spent.
 */
class LogSmsGateway implements SmsGateway
{
    /**
     * Send a text message to a phone number in the +639XXXXXXXXX format.
     */
    public function send(string $phone, string $message): void
    {
        Log::info('SMS to '.$phone.': '.$message);
    }
}
