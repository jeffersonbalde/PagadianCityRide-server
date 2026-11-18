<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ride\CreateRide;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRideRequest;
use App\Http\Resources\Api\V1\RideResource;
use App\Models\Ride;
use App\RideStatus;
use App\TripRange;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RideController extends Controller
{
    /**
     * The trips of the signed-in person, newest first. `scope=ongoing` lists
     * the ride that is going on, and the default `past` lists the finished
     * trips: a passenger also sees the ones they cancelled. `range` keeps the
     * trips of today, of this week or all of them.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'scope' => ['nullable', Rule::in(['ongoing', 'past'])],
            'range' => ['nullable', Rule::enum(TripRange::class)],
        ]);

        $user = $request->user();
        $isDriver = $user->role === UserRole::Driver;

        $rides = ($isDriver ? $user->ridesAsDriver() : $user->ridesAsPassenger())
            ->with(Ride::displayRelations())
            ->inRange(TripRange::tryFrom($validated['range'] ?? '') ?? TripRange::All);

        if (($validated['scope'] ?? 'past') === 'ongoing') {
            $rides->active();
        } else {
            $rides->whereIn('status', $isDriver ? [RideStatus::Completed] : [RideStatus::Completed, RideStatus::Cancelled]);
        }

        return RideResource::collection($rides->latest('id')->paginate(20));
    }

    /**
     * Ask for a ride.
     */
    public function store(StoreRideRequest $request, CreateRide $createRide): JsonResponse
    {
        $ride = $createRide->handle($request->user(), $request->validated());

        return RideResource::make($ride->loadForDisplay())
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show one ride.
     */
    public function show(Ride $ride): RideResource
    {
        Gate::authorize('view', $ride);

        return RideResource::make($ride->loadForDisplay());
    }
}
