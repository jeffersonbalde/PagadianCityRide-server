<?php

namespace App\Actions\Ride;

use App\Events\RideStatusChanged;
use App\Models\Ride;
use App\RideStatus;
use Illuminate\Support\Facades\DB;

class CompleteRide
{
    /**
     * The driver finishes the trip.
     */
    public function handle(Ride $ride): Ride
    {
        return DB::transaction(function () use ($ride): Ride {
            $current = Ride::lockForUpdate()->findOrFail($ride->id);
            $previous = $current->status;

            abort_unless($current->status === RideStatus::InProgress, 409, 'The trip has not started.');

            $current->update([
                'status' => RideStatus::Completed,
                'completed_at' => now(),
            ]);

            RideStatusChanged::dispatch($current, $previous);

            return $current;
        });
    }
}
