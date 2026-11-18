<?php

namespace App;

enum UserRole: string
{
    case Passenger = 'passenger';
    case Driver = 'driver';
    case Admin = 'admin';

    /**
     * Roles a person may pick for themselves when they sign up in the app.
     *
     * @return list<self>
     */
    public static function selfAssignable(): array
    {
        return [self::Passenger, self::Driver];
    }
}
