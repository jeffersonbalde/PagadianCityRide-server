<?php

namespace App\Services\Push;

/**
 * Keeps messages in memory so a test can read what would have been sent.
 */
class ArrayPushGateway implements PushGateway
{
    /**
     * @var list<array{to: string, title: string, body: string, data?: array<string, mixed>}>
     */
    private array $sent = [];

    /**
     * @var list<string>
     */
    private array $unregistered = [];

    /**
     * @param  list<array{to: string, title: string, body: string, data?: array<string, mixed>}>  $messages
     * @return list<string>
     */
    public function send(array $messages): array
    {
        array_push($this->sent, ...$messages);

        return array_values(array_intersect(array_column($messages, 'to'), $this->unregistered));
    }

    /**
     * Every message sent so far, oldest first.
     *
     * @return list<array{to: string, title: string, body: string, data?: array<string, mixed>}>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    /**
     * Pretend that the push service does not know these tokens any more.
     *
     * @param  list<string>  $tokens
     */
    public function unregister(array $tokens): void
    {
        $this->unregistered = $tokens;
    }
}
