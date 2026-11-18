<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RideResource;
use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CurrentRideController extends Controller
{
    /**
     * The ride that is going on for the signed-in person, or nothing when
     * there is none. The app asks for this every few seconds while it waits.
     */
    public function show(Request $request): RideResource|Response
    {
        $user = $request->user();

        $ride = ($user->role === UserRole::Driver ? $user->ridesAsDriver() : $user->ridesAsPassenger())
            ->active()
            ->latest('id')
            ->first();

        if ($ride === null) {
            return response()->noContent();
        }

        return RideResource::make($ride->loadForDisplay());
    }
}
