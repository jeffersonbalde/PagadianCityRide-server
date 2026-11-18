<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Actions\Ride\CompleteRide;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Ride;
use Illuminate\Support\Facades\Gate;

class RideCompletionController extends Controller
{
    /**
     * Finish the trip.
     */
    public function store(Ride $ride, CompleteRide $completeRide): RideResource
    {
        Gate::authorize('complete', $ride);

        return RideResource::make($completeRide->handle($ride)->loadForDisplay());
    }
}
