<?php

namespace App\Filament\Widgets;

use App\DriverStatus;
use App\Filament\Resources\TripResource;
use App\Filament\Resources\UserResource;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use App\RideStatus;
use App\UserRole;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The totals since the app started: the people and the trips.
 */
class UserTotals extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Passengers', User::query()->where('role', UserRole::Passenger)->count())
                ->icon('heroicon-o-users')
                ->url(UserResource::getUrl('index', ['tab' => 'passengers'])),
            Stat::make('Drivers', User::query()->where('role', UserRole::Driver)->count())
                ->description(DriverProfile::query()->where('status', DriverStatus::Approved)->count().' approved')
                ->icon('heroicon-o-user-group')
                ->url(UserResource::getUrl('index', ['tab' => 'drivers'])),
            Stat::make('All trips', Ride::query()->count())
                ->description(Ride::query()->where('status', RideStatus::Completed)->count().' completed')
                ->icon('heroicon-o-map-pin')
                ->url(TripResource::getUrl()),
            Stat::make('Blocked', User::query()->whereNotNull('blocked_at')->count())
                ->icon('heroicon-o-no-symbol')
                ->url(UserResource::getUrl('index', ['tab' => 'blocked'])),
        ];
    }
}
