<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WaitingRideResource;
use App\Models\Ride;
use App\RideStatus;
use App\Support\Distance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WaitingRideController extends Controller
{
    /**
     * The passengers who wait for a tricycle, nearest to the driver first.
     * It uses the last location the driver sent. A passenger the driver gave
     * up is listed again while they still wait.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $driver = $request->user();
        $driverProfile = $driver->driverProfile;

        abort_unless($driverProfile?->is_online && $driverProfile->latitude !== null, 409, 'Go online to see the waiting passengers.');

        $radius = config('rides.search_radius_meters');

        $rides = Ride::query()
            ->with('passenger')
            ->where('status', RideStatus::Requested)
            ->when($radius !== null, function (Builder $query) use ($driverProfile, $radius): void {
                $box = Distance::boundingBox($driverProfile->latitude, $driverProfile->longitude, $radius);

                $query->whereBetween('pickup_latitude', [$box['minLatitude'], $box['maxLatitude']])
                    ->whereBetween('pickup_longitude', [$box['minLongitude'], $box['maxLongitude']]);
            })
            ->get()
            ->each(fn (Ride $ride) => $ride->setAttribute(
                'distance_to_pickup',
                Distance::meters($driverProfile->latitude, $driverProfile->longitude, $ride->pickup_latitude, $ride->pickup_longitude),
            ))
            ->filter(fn (Ride $ride): bool => $radius === null || $ride->distance_to_pickup <= $radius)
            ->sortBy([['distance_to_pickup', 'asc'], ['id', 'asc']])
            ->take(20)
            ->values();

        return WaitingRideResource::collection($rides);
    }
}
