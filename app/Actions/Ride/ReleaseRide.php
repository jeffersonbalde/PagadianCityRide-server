<?php

namespace App\Actions\Ride;

use App\DriverCancelReason;
use App\Events\RideStatusChanged;
use App\Models\Ride;
use App\RideStatus;
use Illuminate\Support\Facades\DB;

class ReleaseRide
{
    /**
     * The driver cancels the pickup. The passenger is not cancelled: the
     * request goes back to the waiting list for every driver near, this one
     * too, because a driver can change their mind. The reason is kept to see
     * patterns.
     */
    public function handle(Ride $ride, DriverCancelReason $reason, ?string $note = null): Ride
    {
        return DB::transaction(function () use ($ride, $reason, $note): Ride {
            $current = Ride::lockForUpdate()->findOrFail($ride->id);
            $previous = $current->status;

            abort_unless(
                in_array($current->status, [RideStatus::Accepted, RideStatus::Arrived], true),
                409,
                'The pickup can no longer be cancelled.',
            );

            $current->driverCancellations()->create([
                'driver_id' => $current->driver_id,
                'reason' => $reason,
                'note' => $note,
            ]);

            $current->update([
                'driver_id' => null,
                'status' => RideStatus::Requested,
                'accepted_at' => null,
                'arrived_at' => null,
            ]);

            RideStatusChanged::dispatch($current, $previous);

            return $current;
        });
    }
}
