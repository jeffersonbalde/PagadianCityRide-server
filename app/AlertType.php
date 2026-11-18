<?php

namespace App;

use App\Models\Ride;

/**
 * Everything the app tells a person about without being asked. The value is
 * what the apps use to pick an icon and a screen. The title and the body are
 * the English text for the alert list and for the push notification.
 */
enum AlertType: string
{
    case RideAccepted = 'ride.accepted';
    case DriverArrived = 'ride.driver-arrived';
    case TripCompleted = 'ride.completed';
    case DriverCancelled = 'ride.driver-cancelled';
    case RideCancelled = 'ride.cancelled';
    case RideExpired = 'ride.expired';
    case NewRideNearby = 'ride.nearby';
    case DriverApproved = 'driver.approved';
    case DriverRejected = 'driver.rejected';
    case ReportHandled = 'report.handled';
    case NewMessage = 'ride.message';

    public function title(): string
    {
        return match ($this) {
            self::RideAccepted => 'Your driver is on the way',
            self::DriverArrived => 'Your driver is here',
            self::TripCompleted => 'You arrived',
            self::DriverCancelled => 'Your driver cancelled',
            self::RideCancelled => 'The passenger cancelled',
            self::RideExpired => 'No driver found',
            self::NewRideNearby => 'A passenger is waiting',
            self::DriverApproved => 'You are approved',
            self::DriverRejected => 'Your application needs changes',
            self::ReportHandled => 'We looked at your report',
            self::NewMessage => 'New message',
        };
    }

    public function body(?Ride $ride = null, ?string $reason = null): string
    {
        $text = match ($this) {
            self::RideAccepted => ($ride?->driver?->name ?? 'A driver').' accepted your request.',
            self::DriverArrived => 'Look for the tricycle at your pickup point.',
            self::TripCompleted => 'Tap to rate your driver.',
            self::DriverCancelled => 'We are looking for another driver for you.',
            self::RideCancelled => 'This ride was cancelled before it started.',
            self::RideExpired => 'No driver took your request. Please try again.',
            self::NewRideNearby => 'Pickup at '.($ride?->pickup_label ?? 'a point near you').'.',
            self::DriverApproved => 'You can now go online and take passengers.',
            self::DriverRejected => 'Open the app to see what to fix.',
            self::ReportHandled => 'Thank you for telling us. Our team has reviewed it.',
            self::NewMessage => 'You have a new message.',
        };

        // A person who cancels can tell why, and the other one reads it in the alert.
        $tellsWhy = in_array($this, [self::DriverCancelled, self::RideCancelled], true);

        return $tellsWhy && filled($reason) ? $text.' Reason: '.trim($reason) : $text;
    }

    /**
     * Whether the alert stays in the alert list. A passing "someone is
     * waiting" or "new chat message" is only worth a sound and a banner.
     */
    public function isStored(): bool
    {
        return ! in_array($this, [self::NewRideNearby, self::NewMessage], true);
    }
}
