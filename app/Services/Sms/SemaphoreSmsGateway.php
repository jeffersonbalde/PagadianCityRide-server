<?php

namespace App\Services\Sms;

use App\Exceptions\SmsDeliveryFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Sends messages through Semaphore's priority route, which is meant for
 * one-time passwords and is not queued behind bulk messages.
 */
class SemaphoreSmsGateway implements SmsGateway
{
    /**
     * Send a text message to a phone number in the +639XXXXXXXXX format.
     *
     * The request is not retried: a retry after a slow response could text the
     * same code twice.
     */
    public function send(string $phone, string $message): void
    {
        $payload = [
            'apikey' => config('services.semaphore.key'),
            'number' => ltrim($phone, '+'),
            'message' => $message,
        ];

        $senderName = config('services.semaphore.sender_name');

        if (filled($senderName)) {
            $payload['sendername'] = $senderName;
        }

        try {
            Http::baseUrl(config('services.semaphore.base_url'))
                ->connectTimeout(3)
                ->timeout(10)
                ->asForm()
                ->post('/priority', $payload)
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw SmsDeliveryFailed::because($exception);
        }
    }
}
