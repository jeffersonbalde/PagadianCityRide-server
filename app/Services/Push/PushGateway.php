<?php

namespace App\Services\Push;

interface PushGateway
{
    /**
     * Send push notifications to phones. Sending is best effort: a failure
     * is reported but never thrown, because the person already gets the
     * alert inside the app.
     *
     * @param  list<array{to: string, title: string, body: string, data?: array<string, mixed>}>  $messages
     * @return list<string> The tokens the push service no longer knows. Delete them.
     */
    public function send(array $messages): array;
}
