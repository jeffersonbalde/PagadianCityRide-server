<?php

namespace App\Services\Sms;

use App\Exceptions\SmsDeliveryFailed;

interface SmsGateway
{
    /**
     * Send a text message to a phone number in the +639XXXXXXXXX format.
     *
     * @throws SmsDeliveryFailed
     */
    public function send(string $phone, string $message): void;
}
