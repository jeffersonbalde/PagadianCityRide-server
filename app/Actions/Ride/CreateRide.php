<?php

namespace App\Actions\Ride;

use App\Events\RideStatusChanged;
use App\Models\Ride;
use App\Models\User;
use App\RideStatus;
use Illuminate\Support\Facades\DB;

class CreateRide
{
    /**
     * Ask for a ride. A passenger has one ride at a time, so a second request
     * is refused while the first one is going on.
     *
     * @param  array{pickup: array{latitude: float, longitude: float, label?: ?string}, destination: array{latitude: float, longitude: float, label: string}, passengers_count: int, note?: ?string, distance_meters?: ?int, duration_seconds?: ?int}  $data
     */
    public function handle(User $passenger, array $data): Ride
    {
        return DB::transaction(function () use ($passenger, $data): Ride {
            User::whereKey($passenger->id)->lockForUpdate()->first();

            abort_if($passenger->ridesAsPassenger()->active()->exists(), 409, 'You already have a ride going on.');

            $ride = $passenger->ridesAsPassenger()->create([
                'status' => RideStatus::Requested,
                'pickup_latitude' => $data['pickup']['latitude'],
                'pickup_longitude' => $data['pickup']['longitude'],
                'pickup_label' => $data['pickup']['label'] ?? null,
                'destination_latitude' => $data['destination']['latitude'],
                'destination_longitude' => $data['destination']['longitude'],
                'destination_label' => $data['destination']['label'],
                'passengers_count' => $data['passengers_count'],
                'note' => $data['note'] ?? null,
                'distance_meters' => $data['distance_meters'] ?? null,
                'duration_seconds' => $data['duration_seconds'] ?? null,
            ]);

            RideStatusChanged::dispatch($ride);

            return $ride;
        });
    }
}
