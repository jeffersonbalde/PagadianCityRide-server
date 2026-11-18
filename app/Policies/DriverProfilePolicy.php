<?php

namespace App\Policies;

use App\DriverStatus;
use App\Models\DriverProfile;
use App\Models\User;
use App\UserRole;
use Illuminate\Auth\Access\Response;

class DriverProfilePolicy
{
    /**
     * Admins read every application, drivers read their own.
     */
    public function view(User $user, DriverProfile $driverProfile): bool
    {
        return $user->role === UserRole::Admin || $driverProfile->user_id === $user->id;
    }

    /**
     * Drivers change their own application until it is sent for review, and
     * again when it was sent back.
     */
    public function update(User $user, DriverProfile $driverProfile): Response
    {
        if ($driverProfile->user_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        return $driverProfile->status->isEditable()
            ? Response::allow()
            : Response::deny('The application can no longer be changed.');
    }

    /**
     * Only an admin reviews an application, and only while it waits for review.
     */
    public function review(User $user, DriverProfile $driverProfile): Response
    {
        if ($user->role !== UserRole::Admin) {
            return Response::deny();
        }

        return $driverProfile->status === DriverStatus::Submitted
            ? Response::allow()
            : Response::deny('This application is not waiting for review.');
    }
}
