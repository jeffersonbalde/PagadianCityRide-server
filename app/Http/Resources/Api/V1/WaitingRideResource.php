<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A request that waits for a driver, as the driver sees it in the list. Load
 * `passenger` and set `distance_to_pickup` before using this resource. The
 * phone number of the passenger is not here: the driver gets it after taking
 * the ride.
 *
 * @mixin Ride
 */
class WaitingRideResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'passenger_name' => $this->passenger->name,
            'passengers_count' => $this->passengers_count,
            'pickup' => [
                'latitude' => $this->pickup_latitude,
                'longitude' => $this->pickup_longitude,
                'label' => $this->pickup_label,
            ],
            // The driver sees where the trip goes on the map before choosing the passenger.
            'destination' => [
                'latitude' => $this->destination_latitude,
                'longitude' => $this->destination_longitude,
                'label' => $this->destination_label,
            ],
            'destination_label' => $this->destination_label,
            'note' => $this->note,
            'distance_to_pickup_meters' => $this->distance_to_pickup,
            'distance_meters' => $this->distance_meters,
            'waiting_seconds' => (int) $this->created_at->diffInSeconds(now()),
            // The moment the passenger asked, so the phone can count the waiting time by itself between two refreshes.
            'requested_at' => $this->created_at->toIso8601String(),
        ];
    }
}
