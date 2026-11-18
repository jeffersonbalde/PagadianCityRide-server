<?php

namespace App\Actions\Ride;

use App\Events\RideStatusChanged;
use App\Models\Ride;
use App\RideStatus;
use Illuminate\Support\Facades\DB;

class StartRide
{
    /**
     * The passenger is on the tricycle and the trip begins.
     */
    public function handle(Ride $ride): Ride
    {
        return DB::transaction(function () use ($ride): Ride {
            $current = Ride::lockForUpdate()->findOrFail($ride->id);
            $previous = $current->status;

            abort_unless($current->status === RideStatus::Arrived, 409, 'The tricycle has not arrived yet.');

            $current->update([
                'status' => RideStatus::InProgress,
                'started_at' => now(),
            ]);

            RideStatusChanged::dispatch($current, $previous);

            return $current;
        });
    }
}
