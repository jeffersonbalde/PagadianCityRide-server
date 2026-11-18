<?php

namespace App\Policies;

use App\Models\Ride;
use App\Models\User;
use App\RideStatus;
use Illuminate\Auth\Access\Response;

class RidePolicy
{
    /**
     * Only the passenger and the driver of a ride can see it. For anyone else
     * it does not exist.
     */
    public function view(User $user, Ride $ride): Response
    {
        return $ride->isParticipant($user) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Either one can cancel before the trip starts.
     */
    public function cancel(User $user, Ride $ride): Response
    {
        if (! $ride->isParticipant($user)) {
            return Response::denyAsNotFound();
        }

        return in_array($ride->status, [RideStatus::Requested, RideStatus::Accepted, RideStatus::Arrived], true)
            ? Response::allow()
            : Response::deny('This ride can no longer be cancelled.');
    }

    /**
     * Either one can start the trip once the tricycle is at the pickup point.
     */
    public function start(User $user, Ride $ride): Response
    {
        if (! $ride->isParticipant($user)) {
            return Response::denyAsNotFound();
        }

        return $ride->status === RideStatus::Arrived
            ? Response::allow()
            : Response::deny('The tricycle has not arrived yet.');
    }

    /**
     * Only the driver of the ride tells that the tricycle arrived.
     */
    public function arrive(User $user, Ride $ride): Response
    {
        if ($ride->driver_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        return $ride->status === RideStatus::Accepted
            ? Response::allow()
            : Response::deny('The ride is not waiting for the driver to arrive.');
    }

    /**
     * Only the driver of the ride finishes the trip.
     */
    public function complete(User $user, Ride $ride): Response
    {
        if ($ride->driver_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        return $ride->status === RideStatus::InProgress
            ? Response::allow()
            : Response::deny('The trip has not started.');
    }

    /**
     * Only the passenger rates the driver, once, after the trip.
     */
    public function rate(User $user, Ride $ride): Response
    {
        if ($ride->passenger_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        if ($ride->status !== RideStatus::Completed) {
            return Response::deny('You can rate the driver after the trip.');
        }

        return $ride->rating === null
            ? Response::allow()
            : Response::deny('You already rated this trip.');
    }

    /**
     * Either person on the ride may report the other one, as soon as there is another person.
     */
    public function report(User $user, Ride $ride): Response
    {
        if (! $ride->isParticipant($user)) {
            return Response::denyAsNotFound();
        }

        return $ride->driver_id === null
            ? Response::deny('There is nobody to report on this ride.')
            : Response::allow();
    }

    /**
     * Only the passenger of the ride looks at the profile of the driver, once a driver took the ride.
     */
    public function viewDriver(User $user, Ride $ride): Response
    {
        if ($ride->passenger_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        return $ride->driver_id === null
            ? Response::deny('No driver took this ride yet.')
            : Response::allow();
    }

    /**
     * Either person reads the chat of the ride, once there is another person.
     */
    public function readMessages(User $user, Ride $ride): Response
    {
        if (! $ride->isParticipant($user)) {
            return Response::denyAsNotFound();
        }

        return $ride->driver_id === null
            ? Response::deny('There is nobody to chat with yet.')
            : Response::allow();
    }

    /**
     * Either person writes in the chat of a ride that had a driver: while the driver is on the way, during the
     * trip, after the trip (for lost things and thanks) and after a cancel too. The chat is closed only when no
     * driver took the ride.
     */
    public function sendMessage(User $user, Ride $ride): Response
    {
        return $this->readMessages($user, $ride);
    }

    /**
     * Only the driver of the ride looks at the profile of the passenger.
     */
    public function viewPassenger(User $user, Ride $ride): Response
    {
        return $ride->driver_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
