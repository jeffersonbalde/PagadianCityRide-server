<?php

namespace App\Services\Sms;

use App\Exceptions\SmsDeliveryFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Sends messages through an Android phone of our own that runs the "SMS Gateway for Android" app. The phone texts
 * with its own SIM, like a person would, so the message reaches every network without a sender name that a telco
 * has to approve. The price is that the phone must stay on, charged and online.
 */
class AndroidPhoneSmsGateway implements SmsGateway
{
    /**
     * A message of 100 or more goes before the others on the phone and is not held back by the delay between texts.
     * A login code must not wait.
     */
    private const PRIORITY = 100;

    /**
     * Send a text message to a phone number in the +639XXXXXXXXX format.
     *
     * The request is not retried: a retry after a slow response could text the same code twice. The message expires
     * when the phone does not pick it up in time, so an old code is not texted hours later.
     */
    public function send(string $phone, string $message): void
    {
        try {
            Http::baseUrl(config('services.android_sms.base_url'))
                ->withBasicAuth((string) config('services.android_sms.username'), (string) config('services.android_sms.password'))
                ->connectTimeout(3)
                ->timeout(10)
                ->asJson()
                ->post('/messages', [
                    'textMessage' => ['text' => $message],
                    'phoneNumbers' => [$phone],
                    'ttl' => (int) config('services.android_sms.ttl_seconds'),
                    'priority' => self::PRIORITY,
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw SmsDeliveryFailed::because($exception);
        }
    }
}
