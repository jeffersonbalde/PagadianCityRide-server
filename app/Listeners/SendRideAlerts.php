<?php

namespace App\Listeners;

use App\AlertType;
use App\Events\RideStatusChanged;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Notifications\Alert;
use App\RideStatus;
use App\Support\Distance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the right person about each step of a ride. Nobody is told about a
 * step they did themselves, and the start of the trip needs no alert: both
 * people are in the tricycle.
 */
class SendRideAlerts implements ShouldQueue
{
    public function handle(RideStatusChanged $event): void
    {
        $ride = $event->ride;

        if ($event->previous === null) {
            $this->tellNearbyDrivers($ride);

            return;
        }

        match ($event->status) {
            RideStatus::Accepted => $ride->passenger->notify(new Alert(AlertType::RideAccepted, $ride)),
            RideStatus::Arrived => $ride->passenger->notify(new Alert(AlertType::DriverArrived, $ride)),
            RideStatus::Completed => $ride->passenger->notify(new Alert(AlertType::TripCompleted, $ride)),
            RideStatus::Expired => $ride->passenger->notify(new Alert(AlertType::RideExpired, $ride)),
            RideStatus::Cancelled => $ride->driver?->notify(new Alert(AlertType::RideCancelled, $ride, reason: $ride->cancel_reason)),
            RideStatus::Requested => $this->tellPassengerAboutLostDriver($ride),
            default => null,
        };
    }

    /**
     * The driver gave the pickup up and the request waits again.
     */
    private function tellPassengerAboutLostDriver(Ride $ride): void
    {
        $cancellation = $ride->driverCancellations()->latest('id')->first();

        $ride->passenger->notify(new Alert(AlertType::DriverCancelled, $ride, reason: $cancellation?->reason->words($cancellation->note)));

        $this->tellNearbyDrivers($ride);
    }

    /**
     * Tell the closest drivers who are free. The ones who gave this pickup up
     * get no new alert: they see the passenger on their map again.
     */
    private function tellNearbyDrivers(Ride $ride): void
    {
        $radius = config('rides.search_radius_meters');

        $drivers = DriverProfile::query()
            ->availableNear($ride->pickup_latitude, $ride->pickup_longitude, $radius)
            ->whereNotIn('user_id', Ride::query()->active()->whereNotNull('driver_id')->select('driver_id'))
            ->whereNotIn('user_id', $ride->driverCancellations()->select('driver_id'))
            ->with('user')
            ->get()
            ->map(fn (DriverProfile $profile): array => [
                'user' => $profile->user,
                'meters' => Distance::meters($ride->pickup_latitude, $ride->pickup_longitude, $profile->latitude, $profile->longitude),
            ])
            ->filter(fn (array $driver): bool => $driver['meters'] <= $radius)
            ->sortBy('meters')
            ->take(config('rides.notify_nearest_drivers'))
            ->pluck('user');

        if ($drivers->isNotEmpty()) {
            Notification::send($drivers, new Alert(AlertType::NewRideNearby, $ride));
        }
    }
}
