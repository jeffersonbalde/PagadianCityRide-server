<?php

use App\Support\PhoneNumber;

it('turns a Philippine mobile number into +639XXXXXXXXX', function (string $input) {
    expect(PhoneNumber::normalize($input))->toBe('+639171234567');
})->with([
    'local format' => '09171234567',
    'local format with spaces' => '0917 123 4567',
    'local format with dashes' => '0917-123-4567',
    'country code without plus' => '639171234567',
    'country code with plus' => '+639171234567',
    'country code with plus and spaces' => '+63 917 123 4567',
    'without the leading zero' => '9171234567',
]);

it('returns null when the input is not a Philippine mobile number', function (string $input) {
    expect(PhoneNumber::normalize($input))->toBeNull();
})->with([
    'empty' => '',
    'letters' => 'not a number',
    'too short' => '091712345',
    'too long' => '091712345678',
    'landline' => '028123456',
    'other country' => '+14155550100',
    'does not start with 9' => '08171234567',
]);
