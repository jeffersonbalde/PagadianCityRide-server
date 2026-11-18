<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Events\DriverLocationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\UpdateLocationRequest;
use App\Http\Resources\Api\V1\Driver\DriverAvailabilityResource;

class LocationController extends Controller
{
    /**
     * Tell where the tricycle is. The app sends this every few seconds while
     * the driver is online.
     */
    public function update(UpdateLocationRequest $request): DriverAvailabilityResource
    {
        $driverProfile = $request->user()->driverProfile;

        abort_unless($driverProfile?->is_online, 409, 'Go online first.');

        $driverProfile->update([
            'latitude' => $request->validated('latitude'),
            'longitude' => $request->validated('longitude'),
            'located_at' => now(),
        ]);

        $ride = $request->user()->ridesAsDriver()->active()->first();

        if ($ride !== null) {
            DriverLocationUpdated::dispatch(
                $ride->id,
                $ride->passenger_id,
                (float) $request->validated('latitude'),
                (float) $request->validated('longitude'),
            );
        }

        return DriverAvailabilityResource::make($driverProfile);
    }
}
