<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends push notifications through the Expo push service. The service takes at
 * most 100 messages in one request and answers with one ticket per message.
 */
class ExpoPushGateway implements PushGateway
{
    private const MAX_MESSAGES_PER_REQUEST = 100;

    public function __construct(
        private string $url,
        private ?string $accessToken = null,
    ) {}

    /**
     * @param  list<array{to: string, title: string, body: string, data?: array<string, mixed>}>  $messages
     * @return list<string>
     */
    public function send(array $messages): array
    {
        $unregistered = [];

        foreach (array_chunk($messages, self::MAX_MESSAGES_PER_REQUEST) as $chunk) {
            array_push($unregistered, ...$this->sendChunk($chunk));
        }

        return $unregistered;
    }

    /**
     * @param  list<array{to: string, title: string, body: string, data?: array<string, mixed>}>  $chunk
     * @return list<string>
     */
    private function sendChunk(array $chunk): array
    {
        $payload = array_map(fn (array $message): array => $message + [
            'sound' => 'default',
            'priority' => 'high',
        ], $chunk);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->when($this->accessToken, fn ($request, string $token) => $request->withToken($token))
                ->post($this->url, $payload)
                ->throw();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        $tickets = $response->json('data', []);
        $unregistered = [];

        foreach ($chunk as $index => $message) {
            $ticket = $tickets[$index] ?? [];

            if (($ticket['status'] ?? null) === 'error' && ($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                $unregistered[] = $message['to'];
            }
        }

        return $unregistered;
    }
}
