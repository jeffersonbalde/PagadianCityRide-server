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
            'phone' => '+639171234567',
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
            'phone' => '+639171234567',
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
                'phone' => '+639171234567',
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

    it('does not change the phone number or the role', function () {
        $user = User::factory()->create(['phone' => '+639171234567']);

        $this->withToken($user->createToken('mobile')->plainTextToken)
            ->putJson(route('api.v1.me.update'), ['name' => 'Maria Santos', 'phone' => '+639999999999', 'role' => 'admin'])
            ->assertOk();

        expect($user->fresh()->only(['phone', 'role']))->toBe(['phone' => '+639171234567', 'role' => UserRole::Passenger]);
    });

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
