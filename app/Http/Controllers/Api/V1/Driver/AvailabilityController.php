<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\DriverStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\UpdateAvailabilityRequest;
use App\Http\Resources\Api\V1\Driver\DriverAvailabilityResource;

class AvailabilityController extends Controller
{
    /**
     * Go online or offline. Only an approved driver goes online, and a driver
     * with a ride going on cannot go offline.
     */
    public function update(UpdateAvailabilityRequest $request): DriverAvailabilityResource
    {
        $user = $request->user();
        $driverProfile = $user->driverProfile;

        abort_unless($driverProfile?->status === DriverStatus::Approved, 403, 'Your application is not approved yet.');

        if ($request->boolean('is_online')) {
            $driverProfile->update([
                'is_online' => true,
                'latitude' => $request->validated('latitude'),
                'longitude' => $request->validated('longitude'),
                'located_at' => now(),
            ]);
        } else {
            abort_if($user->ridesAsDriver()->active()->exists(), 409, 'Finish your trip before you go offline.');

            $driverProfile->update(['is_online' => false]);
        }

        return DriverAvailabilityResource::make($driverProfile);
    }
}
