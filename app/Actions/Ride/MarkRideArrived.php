<?php

namespace App\Actions\Ride;

use App\Events\RideStatusChanged;
use App\Models\Ride;
use App\RideStatus;
use Illuminate\Support\Facades\DB;

class MarkRideArrived
{
    /**
     * The driver tells that the tricycle is at the pickup point.
     */
    public function handle(Ride $ride): Ride
    {
        return DB::transaction(function () use ($ride): Ride {
            $current = Ride::lockForUpdate()->findOrFail($ride->id);
            $previous = $current->status;

            abort_unless($current->status === RideStatus::Accepted, 409, 'The ride is not waiting for the driver to arrive.');

            $current->update([
                'status' => RideStatus::Arrived,
                'arrived_at' => now(),
            ]);

            RideStatusChanged::dispatch($current, $previous);

            return $current;
        });
    }
}
