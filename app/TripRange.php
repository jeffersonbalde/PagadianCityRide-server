<?php

namespace App;

use Illuminate\Support\Carbon;

enum TripRange: string
{
    case Today = 'today';
    case Week = 'week';
    case All = 'all';

    /**
     * The moment the range starts, or null when it has no start.
     */
    public function startsAt(): ?Carbon
    {
        return match ($this) {
            self::Today => now()->startOfDay(),
            self::Week => now()->startOfWeek(),
            self::All => null,
        };
    }
}
