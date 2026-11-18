<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\Alert;
use App\Services\Push\PushGateway;

/**
 * Sends an alert as a push notification to every phone the person signed in
 * on, and forgets the phones that the push service no longer knows.
 */
class ExpoPushChannel
{
    public function __construct(private PushGateway $gateway) {}

    public function send(User $notifiable, Alert $notification): void
    {
        $tokens = $notifiable->deviceTokens()->pluck('token');

        if ($tokens->isEmpty()) {
            return;
        }

        $message = $notification->toExpoPush($notifiable);

        $unregistered = $this->gateway->send(
            $tokens->map(fn (string $token): array => ['to' => $token] + $message)->all(),
        );

        if ($unregistered !== []) {
            DeviceToken::whereIn('token', $unregistered)->delete();
        }
    }
}
