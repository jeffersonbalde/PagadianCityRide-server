<?php

namespace App\Events;

use App\Models\RideMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Someone wrote in the chat of a ride. Only the other person of the ride hears it live, so the message
 * shows in their open chat at once.
 */
class RideMessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public RideMessage $message,
        public int $recipientId,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->recipientId)];
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    /**
     * @return array{id: int, ride_id: int, sender_id: int, body: string|null, image_url: string|null, created_at: string}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'ride_id' => $this->message->ride_id,
            'sender_id' => $this->message->sender_id,
            'body' => $this->message->body,
            'image_url' => $this->message->imageUrl(),
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
