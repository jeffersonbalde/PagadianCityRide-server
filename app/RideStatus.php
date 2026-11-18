<?php

namespace App;

enum RideStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Arrived = 'arrived';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * Statuses of a ride that is still going on.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Requested, self::Accepted, self::Arrived, self::InProgress];
    }

    /**
     * Statuses in which a driver is on the ride.
     *
     * @return list<self>
     */
    public static function withDriver(): array
    {
        return [self::Accepted, self::Arrived, self::InProgress, self::Completed];
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }
}
