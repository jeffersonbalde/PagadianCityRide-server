<?php

use App\Models\EmailLoginCode;
use App\Models\User;
use App\UserRole;

const EMAIL_FOR_TOKEN = 'maria.santos@gmail.com';
const CODE_FOR_TOKEN = '482915';

it('creates a passenger with a verified email and returns a token on the first sign in', function () {
    $code = EmailLoginCode::factory()->create(['email' => EMAIL_FOR_TOKEN, 'code' => CODE_FOR_TOKEN]);

    $this->postJson(route('api.v1.auth.email-token.store'), [
        'email' => 'Maria.Santos@gmail.com',
        'code' => CODE_FOR_TOKEN,
        'device_name' => 'Maria phone',
    ])
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'email_verified', 'phone', 'phone_verified', 'role']])
        ->assertJsonPath('user.email', EMAIL_FOR_TOKEN)
        ->assertJsonPath('user.email_verified', true)
        ->assertJsonPath('user.phone', null)
        ->assertJsonPath('user.phone_verified', false)
        ->assertJsonPath('user.role', 'passenger');

    $user = User::where('email', EMAIL_FOR_TOKEN)->sole();

    expect($user->role)->toBe(UserRole::Passenger);
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->phone)->toBeNull();
    expect($user->tokens()->sole()->name)->toBe('Maria phone');
    expect($code->fresh()->consumed_at)->not->toBeNull();
});

it('creates a driver when the person signs up as a driver', function () {
    EmailLoginCode::factory()->create(['email' => EMAIL_FOR_TOKEN, 'code' => CODE_FOR_TOKEN]);

    $this->postJson(route('api.v1.auth.email-token.store'), [
        'email' => EMAIL_FOR_TOKEN,
        'code' => CODE_FOR_TOKEN,
        'role' => 'driver',
    ])->assertOk()->assertJsonPath('user.role', 'driver');
});

it('keeps the role of a person who already has an account', function () {
    $driver = User::factory()->driver()->create(['email' => EMAIL_FOR_TOKEN]);
    EmailLoginCode::factory()->create(['email' => EMAIL_FOR_TOKEN, 'code' => CODE_FOR_TOKEN]);

    $this->postJson(route('api.v1.auth.email-token.store'), [
        'email' => EMAIL_FOR_TOKEN,
        'code' => CODE_FOR_TOKEN,
        'role' => 'passenger',
    ])->assertOk()->assertJsonPath('user.id', $driver->id)->assertJsonPath('user.role', 'driver');

    $this->assertDatabaseCount('users', 1);
});

it('returns 422 when the role is not one a person can pick', function (string $role) {
    EmailLoginCode::factory()->create(['email' => EMAIL_FOR_TOKEN, 'code' => CODE_FOR_TOKEN]);

    $this->postJson(route('api.v1.auth.email-token.store'), [
        'email' => EMAIL_FOR_TOKEN,
        'code' => CODE_FOR_TOKEN,
        'role' => $role,
    ])->assertUnprocessable()->assertJsonValidationErrors(['role']);

    $this->assertDatabaseCount('users', 0);
})->with(['admin' => 'admin', 'unknown' => 'owner']);

it('returns 422 and counts the attempt when the code is wrong', function () {
    $code = EmailLoginCode::factory()->create(['email' => EMAIL_FOR_TOKEN, 'code' => CODE_FOR_TOKEN]);

    $this->postJson(route('api.v1.auth.email-token.store'), ['email' => EMAIL_FOR_TOKEN, 'code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'The code is wrong or has expired.']);

    expect($code->fresh()->attempts)->toBe(1);
    $this->assertDatabaseCount('users', 0);
});

it('returns 422 when the code is not six digits', function (string $code) {
    $this->postJson(route('api.v1.auth.email-token.store'), ['email' => EMAIL_FOR_TOKEN, 'code' => $code])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
})->with(['letters' => 'abcdef', 'short' => '1234', 'long' => '1234567']);

it('returns 422 when the code is expired, used or locked', function (string $state) {
    EmailLoginCode::factory()->{$state}()->create(['email' => EMAIL_FOR_TOKEN, 'code' => CODE_FOR_TOKEN]);

    $this->postJson(route('api.v1.auth.email-token.store'), ['email' => EMAIL_FOR_TOKEN, 'code' => CODE_FOR_TOKEN])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'The code is wrong or has expired.']);

    $this->assertDatabaseCount('users', 0);
})->with(['expired', 'consumed', 'locked']);

it('does not accept the code of another address', function () {
    EmailLoginCode::factory()->create(['email' => 'jose@example.com', 'code' => CODE_FOR_TOKEN]);

    $this->postJson(route('api.v1.auth.email-token.store'), ['email' => EMAIL_FOR_TOKEN, 'code' => CODE_FOR_TOKEN])
        ->assertUnprocessable();
});

it('does not accept a code twice', function () {
    EmailLoginCode::factory()->create(['email' => EMAIL_FOR_TOKEN, 'code' => CODE_FOR_TOKEN]);
    $body = ['email' => EMAIL_FOR_TOKEN, 'code' => CODE_FOR_TOKEN];

    $this->postJson(route('api.v1.auth.email-token.store'), $body)->assertOk();
    $this->postJson(route('api.v1.auth.email-token.store'), $body)->assertUnprocessable();
});
