<?php

use App\Models\User;
use App\UserRole;

it('creates an admin for a phone number that has no account', function () {
    $this->artisan('app:make-admin', ['phone' => '0917 123 4567'])
        ->expectsOutput('+639171234567 is now an admin.')
        ->assertSuccessful();

    $admin = User::where('phone', '+639171234567')->sole();

    expect($admin->role)->toBe(UserRole::Admin);
});

it('makes a person who already has an account an admin without creating another one', function () {
    $user = User::factory()->create(['phone' => '+639171234567']);

    $this->artisan('app:make-admin', ['phone' => '09171234567'])->assertSuccessful();

    expect($user->fresh()->role)->toBe(UserRole::Admin);
    $this->assertDatabaseCount('users', 1);
});

it('fails when the phone number is not a Philippine mobile number', function () {
    $this->artisan('app:make-admin', ['phone' => '12345'])
        ->expectsOutput('That is not a Philippine mobile number.')
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
});
