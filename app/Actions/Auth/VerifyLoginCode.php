<?php

namespace App\Actions\Auth;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerifyLoginCode
{
    /**
     * Check the code and return the person it belongs to.
     *
     * A person who signs in for the first time is created with the given role.
     * An existing person keeps the role they already have. A wrong code counts
     * as an attempt, and the code stops working after too many attempts.
     * Returns null when the code is wrong, expired, used or locked.
     */
    public function handle(string $phone, string $code, UserRole $role): ?User
    {
        if ($this->acceptsAnyCode()) {
            return $this->signIn($phone, $role);
        }

        return DB::transaction(function () use ($phone, $code, $role): ?User {
            $verificationCode = PhoneVerificationCode::usable()
                ->where('phone', $phone)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($verificationCode === null) {
                return null;
            }

            if (! Hash::check($code, $verificationCode->code)) {
                $verificationCode->increment('attempts');

                return null;
            }

            $verificationCode->update(['consumed_at' => now()]);

            return $this->signIn($phone, $role);
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

    private function signIn(string $phone, UserRole $role): User
    {
        $user = User::firstOrCreate(['phone' => $phone], ['role' => $role]);

        if ($user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        return $user;
    }
}
