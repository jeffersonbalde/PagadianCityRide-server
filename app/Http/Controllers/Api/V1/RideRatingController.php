<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ride\RateRide;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRideRatingRequest;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RideRatingController extends Controller
{
    /**
     * The passenger rates the driver after the trip.
     */
    public function store(StoreRideRatingRequest $request, Ride $ride, RateRide $rateRide): JsonResponse
    {
        Gate::authorize('rate', $ride);

        return RideResource::make(
            $rateRide->handle($ride, $request->validated('rating'), $request->validated('review'))->loadForDisplay(),
        )->response()->setStatusCode(201);
    }
}
