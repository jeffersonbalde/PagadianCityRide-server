<?php

namespace App\Actions\Ride;

use App\Models\Ride;
use App\RideStatus;
use Illuminate\Support\Facades\DB;

class RateRide
{
    /**
     * The passenger rates the driver once, after the trip.
     */
    public function handle(Ride $ride, int $rating, ?string $review = null): Ride
    {
        return DB::transaction(function () use ($ride, $rating, $review): Ride {
            $current = Ride::lockForUpdate()->findOrFail($ride->id);

            abort_unless($current->status === RideStatus::Completed, 409, 'You can rate the driver after the trip.');
            abort_if($current->rating !== null, 409, 'You already rated this trip.');

            $current->update([
                'rating' => $rating,
                'review' => $review,
                'rated_at' => now(),
            ]);

            return $current;
        });
    }
}
