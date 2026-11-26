<?php

namespace App\Actions\User;

use App\Actions\Ride\CancelRide;
use App\Actions\Ride\ReleaseRide;
use App\DriverCancelReason;
use App\Events\RideStatusChanged;
use App\Models\Ride;
use App\Models\User;
use App\RideStatus;
use App\UserRole;
use Illuminate\Support\Facades\DB;

class BlockUser
{
    public const CLOSED_REASON = 'Closed by the admin';

    public function __construct(private CancelRide $cancelRide, private ReleaseRide $releaseRide) {}

    /**
     * Stop a person from using the app. They are signed out on every phone, a driver goes offline, and their
     * trips that are still going on are ended, so nobody is left waiting for them.
     */
    public function handle(User $user, User $admin, string $reason): void
    {
        abort_if($user->role === UserRole::Admin, 403, 'An admin account cannot be blocked.');
        abort_if($user->isBlocked(), 409, 'This person is already blocked.');

        DB::transaction(function () use ($user, $admin, $reason): void {
            $user->forceFill(['blocked_at' => now(), 'blocked_reason' => $reason, 'blocked_by' => $admin->id])->save();
            $user->tokens()->delete();
            $user->driverProfile()->update(['is_online' => false]);

            $user->ridesAsPassenger()->active()->get()->each(fn (Ride $ride) => $this->endTripOfPassenger($ride));
            $user->ridesAsDriver()->active()->get()->each(fn (Ride $ride) => $this->endTripOfDriver($ride));

            $user->deviceTokens()->delete();
        });
    }

    private function endTripOfPassenger(Ride $ride): void
    {
        if ($ride->status === RideStatus::InProgress) {
            $this->close($ride);

            return;
        }

        $this->cancelRide->handle($ride, self::CLOSED_REASON);
    }

    /**
     * A pickup that did not start yet goes back to the waiting list, so another driver can take the passenger.
     */
    private function endTripOfDriver(Ride $ride): void
    {
        if ($ride->status === RideStatus::InProgress) {
            $this->close($ride);

            return;
        }

        $this->releaseRide->handle($ride, DriverCancelReason::Other, 'The driver is no longer available');
    }

    /**
     * A trip that already started cannot be cancelled in the app, so the admin closes it.
     */
    private function close(Ride $ride): void
    {
        $previous = $ride->status;

        $ride->update(['status' => RideStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => self::CLOSED_REASON]);

        RideStatusChanged::dispatch($ride, $previous);
    }
}
