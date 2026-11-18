<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The driver of a ride that is going on moved. Only the passenger of the ride
 * hears it, so the tricycle can move on the passenger's map.
 */
class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public int $rideId,
        public int $passengerId,
        public float $latitude,
        public float $longitude,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->passengerId)];
    }

    public function broadcastAs(): string
    {
        return 'driver.location';
    }

    /**
     * @return array{ride_id: int, latitude: float, longitude: float}
     */
    public function broadcastWith(): array
    {
        return [
            'ride_id' => $this->rideId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
