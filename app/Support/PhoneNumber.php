<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Turn a Philippine mobile number typed in any common way into +639XXXXXXXXX.
     *
     * Accepts 0917 123 4567, 639171234567, +63 917 123 4567 and 9171234567.
     * Returns null when the input is not a Philippine mobile number.
     */
    public static function normalize(string $input): ?string
    {
        $digits = preg_replace('/[\s\-().]/', '', $input);

        if (preg_match('/^(?:\+?63|0)?(9\d{9})$/', $digits, $matches) !== 1) {
            return null;
        }

        return '+63'.$matches[1];
    }
}
