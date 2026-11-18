<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Actions\Ride\MarkRideArrived;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Ride;
use Illuminate\Support\Facades\Gate;

class RideArrivalController extends Controller
{
    /**
     * The tricycle is at the pickup point.
     */
    public function store(Ride $ride, MarkRideArrived $markRideArrived): RideResource
    {
        Gate::authorize('arrive', $ride);

        return RideResource::make($markRideArrived->handle($ride)->loadForDisplay());
    }
}
