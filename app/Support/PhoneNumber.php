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

    /**
     * The stored +639XXXXXXXXX number the way people write it here, 0917 123 4567. Anything else is returned as it is.
     */
    public static function local(?string $number): ?string
    {
        if ($number === null || preg_match('/^\+63(9\d{2})(\d{3})(\d{4})$/', $number, $matches) !== 1) {
            return $number;
        }

        return '0'.$matches[1].' '.$matches[2].' '.$matches[3];
    }
}
