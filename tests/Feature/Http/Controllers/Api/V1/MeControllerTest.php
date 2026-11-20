<?php

use App\DriverStatus;
use App\Models\DriverProfile;
use App\Models\User;
use App\UserRole;

it('returns 401 without a token', function () {
    $this->getJson(route('api.v1.me.show'))->assertUnauthorized();
});

it('returns 401 as JSON even when the client does not ask for JSON', function () {
    $this->get(route('api.v1.me.show'))
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('returns the signed-in person and nothing private', function () {
    $user = User::factory()->create([
        'name' => 'Maria Santos',
        'phone' => '+639171234567',
    ]);

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->getJson(route('api.v1.me.show'))
        ->assertOk()
        ->assertExactJson([
            'id' => $user->id,
            'name' => 'Maria Santos',
            'email' => $user->email,
            'email_verified' => true,
            'phone' => '+639171234567',
            'phone_verified' => true,
            'role' => 'passenger',
        ]);
});

it('returns no application status for a driver who has not started it', function () {
    $driver = User::factory()->driver()->create(['name' => 'Ramil Mendoza', 'phone' => '+639171234567']);

    $this->withToken($driver->createToken('mobile')->plainTextToken)
        ->getJson(route('api.v1.me.show'))
        ->assertOk()
        ->assertExactJson([
            'id' => $driver->id,
            'name' => 'Ramil Mendoza',
            'email' => $driver->email,
            'email_verified' => true,
            'phone' => '+639171234567',
            'phone_verified' => true,
            'role' => 'driver',
            'driver_status' => null,
        ]);
});

it('returns the application status of a driver', function (DriverStatus $status) {
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->for($driver)->create(['status' => $status]);

    $this->withToken($driver->createToken('mobile')->plainTextToken)
        ->getJson(route('api.v1.me.show'))
        ->assertOk()
        ->assertJsonPath('driver_status', $status->value);
})->with(DriverStatus::cases());

it('returns 401 after the token was revoked', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile');
    $token->accessToken->delete();

    $this->withToken($token->plainTextToken)
        ->getJson(route('api.v1.me.show'))
        ->assertUnauthorized();
});

describe('update', function () {
    it('saves the name of a new account', function () {
        $user = User::factory()->create(['name' => null, 'phone' => '+639171234567']);

        $this->withToken($user->createToken('mobile')->plainTextToken)
            ->putJson(route('api.v1.me.update'), ['name' => '  Maria Santos '])
            ->assertOk()
            ->assertExactJson([
                'id' => $user->id,
                'name' => 'Maria Santos',
                'email' => $user->email,
                'email_verified' => true,
                'phone' => '+639171234567',
                'phone_verified' => true,
                'role' => 'passenger',
            ]);

        expect($user->fresh()->name)->toBe('Maria Santos');
    });

    it('lets a driver change the name and keeps the application status in the answer', function () {
        $driver = User::factory()->driver()->create(['name' => 'Ramil']);
        DriverProfile::factory()->for($driver)->submitted()->create();

        $this->withToken($driver->createToken('mobile')->plainTextToken)
            ->putJson(route('api.v1.me.update'), ['name' => 'Ramil Mendoza'])
            ->assertOk()
            ->assertJsonPath('name', 'Ramil Mendoza')
            ->assertJsonPath('driver_status', 'submitted');
    });

    it('does not change the role or the email', function () {
        $user = User::factory()->create(['email' => 'maria@example.com']);

        $this->withToken($user->createToken('mobile')->plainTextToken)
            ->putJson(route('api.v1.me.update'), ['name' => 'Maria Santos', 'role' => 'admin', 'email' => 'other@example.com'])
            ->assertOk();

        expect($user->fresh()->only(['email', 'role']))->toBe(['email' => 'maria@example.com', 'role' => UserRole::Passenger]);
    });

    it('keeps the phone number when none is sent', function () {
        $user = User::factory()->create(['phone' => '+639171234567']);

        $this->withToken($user->createToken('mobile')->plainTextToken)
            ->putJson(route('api.v1.me.update'), ['name' => 'Maria Santos'])
            ->assertOk()
            ->assertJsonPath('phone', '+639171234567')
            ->assertJsonPath('phone_verified', true);
    });

    it('saves a phone number typed in any way, and does not count it as verified', function () {
        $user = User::factory()->create(['phone' => null, 'phone_verified_at' => null]);

        $this->withToken($user->createToken('mobile')->plainTextToken)
            ->putJson(route('api.v1.me.update'), ['name' => 'Maria Santos', 'phone' => '0917 123 4567'])
            ->assertOk()
            ->assertJsonPath('phone', '+639171234567')
            ->assertJsonPath('phone_verified', false);

        expect($user->fresh()->phone)->toBe('+639171234567');
    });

    it('clears the verification when the phone number changes, and keeps it when the same number is sent again', function () {
        $user = User::factory()->create(['phone' => '+639171234567']);
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->putJson(route('api.v1.me.update'), ['name' => 'Maria Santos', 'phone' => '09171234567'])
            ->assertOk()
            ->assertJsonPath('phone_verified', true);

        $this->withToken($token)
            ->putJson(route('api.v1.me.update'), ['name' => 'Maria Santos', 'phone' => '09181234567'])
            ->assertOk()
            ->assertJsonPath('phone', '+639181234567')
            ->assertJsonPath('phone_verified', false);
    });

    it('returns 422 when the phone number is not valid or belongs to someone else', function (string $phone, string $message) {
        User::factory()->create(['phone' => '+639181234567']);
        $user = User::factory()->create(['phone' => null]);

        $this->withToken($user->createToken('mobile')->plainTextToken)
            ->putJson(route('api.v1.me.update'), ['name' => 'Maria Santos', 'phone' => $phone])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => $message]);

        expect($user->fresh()->phone)->toBeNull();
    })->with([
        'not a mobile number' => ['12345', 'Enter a valid Philippine mobile number, for example 0917 123 4567.'],
        'used by another account' => ['0918 123 4567', 'This mobile number is already used by another account.'],
    ]);

    it('returns 422 when the name is missing, too short or too long', function (array $payload, string $message) {
        $user = User::factory()->create(['name' => null]);

        $this->withToken($user->createToken('mobile')->plainTextToken)
            ->putJson(route('api.v1.me.update'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => $message]);

        expect($user->fresh()->name)->toBeNull();
    })->with([
        'missing' => [[], 'Please tell us your name.'],
        'one letter' => [['name' => 'M'], 'Your name needs at least 2 letters.'],
        'too long' => [['name' => str_repeat('a', 61)], 'Your name can have at most 60 characters.'],
    ]);

    it('returns 401 without a token', function () {
        $this->putJson(route('api.v1.me.update'), ['name' => 'Maria Santos'])->assertUnauthorized();
    });
});
