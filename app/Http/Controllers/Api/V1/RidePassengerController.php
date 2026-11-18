<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\RideStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RidePassengerController extends Controller
{
    /**
     * The profile of the passenger of a ride, for the driver of that ride: the name, the contact number, how long
     * the passenger has used the app, how many trips were finished, and how many of them with this driver.
     */
    public function show(Ride $ride): JsonResponse
    {
        Gate::authorize('viewPassenger', $ride);

        $passenger = $ride->passenger;

        return response()->json([
            'name' => $passenger->name,
            'phone' => $passenger->phone,
            'phone_verified' => $passenger->phone_verified_at !== null,
            'member_since' => $passenger->created_at->toIso8601String(),
            'trips_completed_count' => Ride::query()->where('passenger_id', $passenger->id)->where('status', RideStatus::Completed)->count(),
            'trips_together' => Ride::query()->where('passenger_id', $passenger->id)->where('driver_id', $ride->driver_id)->where('status', RideStatus::Completed)->count(),
        ]);
    }
}
