<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ride\StartRide;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Ride;
use Illuminate\Support\Facades\Gate;

class RideStartController extends Controller
{
    /**
     * The passenger is on the tricycle, the trip begins.
     */
    public function store(Ride $ride, StartRide $startRide): RideResource
    {
        Gate::authorize('start', $ride);

        return RideResource::make($startRide->handle($ride)->loadForDisplay());
    }
}
