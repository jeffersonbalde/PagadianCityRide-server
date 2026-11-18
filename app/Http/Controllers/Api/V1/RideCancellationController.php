<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ride\CancelRide;
use App\Actions\Ride\ReleaseRide;
use App\DriverCancelReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRideCancellationRequest;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Ride;
use Illuminate\Support\Facades\Gate;

class RideCancellationController extends Controller
{
    /**
     * Cancel before the trip starts. A passenger cancels the request. A driver
     * gives the pickup up, and the request goes back to the waiting list.
     */
    public function store(
        StoreRideCancellationRequest $request,
        Ride $ride,
        CancelRide $cancelRide,
        ReleaseRide $releaseRide,
    ): RideResource {
        Gate::authorize('cancel', $ride);

        $ride = $request->isDriver()
            ? $releaseRide->handle($ride, DriverCancelReason::from($request->validated('reason')), $request->validated('note'))
            : $cancelRide->handle($ride, $request->validated('reason'));

        return RideResource::make($ride->loadForDisplay());
    }
}
