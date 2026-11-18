<?php

namespace App\Services\Sms;

/**
 * Keeps messages in memory so a test can read what would have been sent.
 */
class ArraySmsGateway implements SmsGateway
{
    /**
     * @var list<array{phone: string, message: string}>
     */
    private array $sent = [];

    /**
     * Send a text message to a phone number in the +639XXXXXXXXX format.
     */
    public function send(string $phone, string $message): void
    {
        $this->sent[] = ['phone' => $phone, 'message' => $message];
    }

    /**
     * Every message sent so far, oldest first.
     *
     * @return list<array{phone: string, message: string}>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    /**
     * The six digit code in the last message sent to the phone number.
     */
    public function lastCodeFor(string $phone): ?string
    {
        foreach (array_reverse($this->sent) as $sms) {
            if ($sms['phone'] === $phone && preg_match('/\b(\d{6})\b/', $sms['message'], $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }
}
