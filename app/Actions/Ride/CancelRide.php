<?php

namespace App\Actions\Ride;

use App\Events\RideStatusChanged;
use App\Models\Ride;
use App\RideStatus;
use Illuminate\Support\Facades\DB;

class CancelRide
{
    /**
     * The passenger cancels the request, before the trip starts.
     */
    public function handle(Ride $ride, ?string $reason = null): Ride
    {
        return DB::transaction(function () use ($ride, $reason): Ride {
            $current = Ride::lockForUpdate()->findOrFail($ride->id);
            $previous = $current->status;

            abort_unless(
                in_array($current->status, [RideStatus::Requested, RideStatus::Accepted, RideStatus::Arrived], true),
                409,
                'This ride can no longer be cancelled.',
            );

            $current->update([
                'status' => RideStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            RideStatusChanged::dispatch($current, $previous);

            return $current;
        });
    }
}
