<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Actions\Ride\AcceptRide;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideAcceptanceController extends Controller
{
    /**
     * Pick up a waiting passenger.
     */
    public function store(Request $request, Ride $ride, AcceptRide $acceptRide): JsonResponse
    {
        return RideResource::make($acceptRide->handle($ride, $request->user())->loadForDisplay())
            ->response()
            ->setStatusCode(201);
    }
}
