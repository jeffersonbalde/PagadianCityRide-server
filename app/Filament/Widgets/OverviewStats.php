<?php

namespace App\Filament\Widgets;

use App\DriverStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\RideStatus;
use App\TripRange;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The numbers of the day: who is online, who waits, the trips of today and how long a passenger waits.
 */
class OverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $acceptedToday = Ride::query()
            ->inRange(TripRange::Today)
            ->whereNotNull('accepted_at')
            ->get(['created_at', 'accepted_at']);

        $averageWait = $acceptedToday->isEmpty()
            ? null
            : round($acceptedToday->avg(fn (Ride $ride): int => $ride->created_at->diffInSeconds($ride->accepted_at, true)) / 60, 1);

        return [
            Stat::make('Drivers online', DriverProfile::query()
                ->where('status', DriverStatus::Approved)
                ->where('is_online', true)
                ->where('located_at', '>=', now()->subMinutes(config('rides.driver_location_stale_after_minutes')))
                ->count())
                ->icon('heroicon-o-signal'),
            Stat::make('Passengers waiting', Ride::query()->where('status', RideStatus::Requested)->count())
                ->icon('heroicon-o-user'),
            Stat::make('Trips today', Ride::query()->inRange(TripRange::Today)->where('status', RideStatus::Completed)->count())
                ->icon('heroicon-o-flag'),
            Stat::make('Average wait', $averageWait === null ? '—' : $averageWait.' min')
                ->description('For trips picked up today')
                ->icon('heroicon-o-clock'),
        ];
    }
}
