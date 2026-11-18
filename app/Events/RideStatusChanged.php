<?php

namespace App\Events;

use App\Models\Ride;
use App\RideStatus;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ride was requested (`previous` is null) or moved to another status. The
 * people on the ride hear it live and the app refetches the ride; the
 * `SendRideAlerts` listener sends the alerts and push notifications.
 */
class RideStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * The status the ride moved to. It is kept here because the ride loaded
     * by a queued listener may have moved on again.
     */
    public RideStatus $status;

    public function __construct(
        public Ride $ride,
        public ?RideStatus $previous = null,
    ) {
        $this->status = $ride->status;
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return collect([$this->ride->passenger_id, $this->ride->driver_id])
            ->filter()
            ->map(fn (int $id): PrivateChannel => new PrivateChannel('App.Models.User.'.$id))
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'ride.updated';
    }

    /**
     * @return array{ride_id: int, status: string}
     */
    public function broadcastWith(): array
    {
        return [
            'ride_id' => $this->ride->id,
            'status' => $this->status->value,
        ];
    }
}
