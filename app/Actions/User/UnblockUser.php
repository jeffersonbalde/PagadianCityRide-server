<?php

namespace App\Actions\User;

use App\Models\User;

class UnblockUser
{
    /**
     * Let a blocked person sign in again.
     */
    public function handle(User $user): void
    {
        abort_unless($user->isBlocked(), 409, 'This person is not blocked.');

        $user->forceFill(['blocked_at' => null, 'blocked_reason' => null, 'blocked_by' => null])->save();
    }
}
