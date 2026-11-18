<?php

namespace App\Notifications;

use App\AlertType;
use App\Models\Ride;
use App\Models\User;
use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells a person about something that happened, in the alert list (when the
 * type is kept), live on the open app and as a push notification.
 */
class Alert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AlertType $type,
        public ?Ride $ride = null,
        /** The words of the alert, when they are not the fixed text of the type, like a chat message. */
        public ?string $text = null,
        /** Why the ride was cancelled, for the cancel alerts. */
        public ?string $reason = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $this->type->isStored()
            ? ['database', 'broadcast', ExpoPushChannel::class]
            : ['broadcast', ExpoPushChannel::class];
    }

    /**
     * What is saved in the alert list and sent to the open app.
     *
     * @return array{type: string, title: string, body: string, ride_id: int|null}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'type' => $this->type->value,
            'title' => $this->type->title(),
            'body' => $this->text ?? $this->type->body($this->ride, $this->reason),
            'ride_id' => $this->ride?->id,
        ];
    }

    /**
     * The push notification, as the Expo service wants it.
     *
     * @return array{title: string, body: string, data: array{type: string, ride_id: int|null}}
     */
    public function toExpoPush(User $notifiable): array
    {
        return [
            'title' => $this->type->title(),
            'body' => $this->text ?? $this->type->body($this->ride, $this->reason),
            'data' => [
                'type' => $this->type->value,
                'ride_id' => $this->ride?->id,
            ],
        ];
    }

    /**
     * Name the live message after the alert type, so the app can tell the
     * messages apart without knowing PHP class names.
     */
    public function broadcastType(): string
    {
        return $this->type->value;
    }
}
