<?php

namespace App\Actions\Ride;

use App\DriverStatus;
use App\Events\RideStatusChanged;
use App\Models\Ride;
use App\Models\User;
use App\RideStatus;
use Illuminate\Support\Facades\DB;

class AcceptRide
{
    /**
     * A driver picks up a waiting passenger.
     *
     * Only one driver can win a request: the ride row is locked, so a second
     * driver who taps at the same moment is told the passenger is taken. A
     * driver has one ride at a time. A driver who cancelled this pickup can
     * take it again while the passenger still waits.
     */
    public function handle(Ride $ride, User $driver): Ride
    {
        return DB::transaction(function () use ($ride, $driver): Ride {
            User::whereKey($driver->id)->lockForUpdate()->first();

            $driverProfile = $driver->driverProfile;

            abort_unless($driverProfile?->status === DriverStatus::Approved, 403, 'Your application is not approved yet.');
            abort_unless($driverProfile->is_online, 409, 'Go online to take passengers.');
            abort_if($driver->ridesAsDriver()->active()->exists(), 409, 'Finish your trip first.');

            $current = Ride::lockForUpdate()->findOrFail($ride->id);
            $previous = $current->status;

            abort_unless($current->status === RideStatus::Requested, 409, 'This passenger was already picked up.');
            abort_if($current->passenger_id === $driver->id, 403, 'You cannot pick up your own request.');

            $current->update([
                'driver_id' => $driver->id,
                'status' => RideStatus::Accepted,
                'accepted_at' => now(),
            ]);

            RideStatusChanged::dispatch($current, $previous);

            return $current;
        });
    }
}
