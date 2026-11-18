<?php

namespace App;

enum DriverCancelReason: string
{
    case PassengerNotHere = 'passenger_not_here';
    case TooFar = 'too_far';
    case TricycleProblem = 'tricycle_problem';
    case Other = 'other';

    /**
     * The reason in words, for the passenger who reads the alert. "Other" is the note that the driver wrote.
     */
    public function words(?string $note = null): string
    {
        return match ($this) {
            self::PassengerNotHere => 'The passenger was not at the pick-up point',
            self::TooFar => 'The pick-up point is too far',
            self::TricycleProblem => 'Tricycle problem',
            self::Other => filled($note) ? trim($note) : 'Other reason',
        };
    }
}
