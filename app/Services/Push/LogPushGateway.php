<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;

/**
 * Writes the push notifications to the log. For development without an Expo
 * account.
 */
class LogPushGateway implements PushGateway
{
    /**
     * @param  list<array{to: string, title: string, body: string, data?: array<string, mixed>}>  $messages
     * @return list<string>
     */
    public function send(array $messages): array
    {
        foreach ($messages as $message) {
            Log::info('Push to '.$message['to'].': '.$message['title'].' - '.$message['body']);
        }

        return [];
    }
}
