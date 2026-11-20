<?php

namespace App\Actions\Auth;

use App\Models\EmailLoginCode;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerifyEmailLoginCode
{
    /**
     * Check the code and return the person it belongs to.
     *
     * A person who signs in for the first time is created with the given role.
     * An existing person keeps the role they already have. A wrong code counts
     * as an attempt, and the code stops working after too many attempts.
     * Returns null when the code is wrong, expired, used or locked.
     */
    public function handle(string $email, string $code, UserRole $role): ?User
    {
        if ($this->acceptsAnyCode()) {
            return $this->signIn($email, $role);
        }

        return DB::transaction(function () use ($email, $code, $role): ?User {
            $loginCode = EmailLoginCode::usable()
                ->where('email', $email)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($loginCode === null) {
                return null;
            }

            if (! Hash::check($code, $loginCode->code)) {
                $loginCode->increment('attempts');

                return null;
            }

            $loginCode->update(['consumed_at' => now()]);

            return $this->signIn($email, $role);
        });
    }

    /**
     * The testing shortcut: any code is good. It needs the setting AND a local environment, so a
     * forgotten setting on a real server does nothing.
     */
    private function acceptsAnyCode(): bool
    {
        return config('services.sms.accept_any_code') === true && app()->environment('local');
    }

    private function signIn(string $email, UserRole $role): User
    {
        $user = User::firstOrCreate(['email' => $email], ['role' => $role]);

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }
}
