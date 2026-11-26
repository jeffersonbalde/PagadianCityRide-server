<?php

namespace App\Actions\User;

use App\Models\User;
use App\UserRole;

class UpdateUserDetails
{
    /**
     * An admin corrects the name or the mobile number of a person. A changed number is not verified any more.
     */
    public function handle(User $user, string $name, ?string $phone): void
    {
        abort_if($user->role === UserRole::Admin, 403, 'An admin account cannot be changed here.');

        $user->name = $name;

        if ($user->phone !== $phone) {
            $user->phone = $phone;
            $user->forceFill(['phone_verified_at' => null]);
        }

        $user->save();
    }
}
