<?php

use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function approvedDriver(): User
{
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->for($driver)->approved()->create();

    return $driver;
}

it('puts the driver online at the place he is', function () {
    $this->freezeTime();
    $driver = approvedDriver();

    Sanctum::actingAs($driver);

    $this->putJson(route('api.v1.driver.availability.update'), ['is_online' => true, 'latitude' => 7.8231, 'longitude' => 123.4402])
        ->assertOk()
        ->assertExactJson([
            'is_online' => true,
            'latitude' => 7.8231,
            'longitude' => 123.4402,
            'located_at' => now()->toIso8601String(),
        ]);

    expect($driver->driverProfile->fresh()->only(['is_online', 'latitude', 'longitude']))
        ->toBe(['is_online' => true, 'latitude' => 7.8231, 'longitude' => 123.4402]);
});

it('puts the driver offline and keeps the last location off the map', function () {
    $driver = onlineDriver();

    Sanctum::actingAs($driver);

    $this->putJson(route('api.v1.driver.availability.update'), ['is_online' => false])
        ->assertOk()
        ->assertJsonPath('is_online', false);

    expect($driver->driverProfile->fresh()->is_online)->toBeFalse();
});

it('returns 409 when the driver goes offline during a trip', function (string $state) {
    $driver = onlineDriver();
    Ride::factory()->{$state}($driver)->create();

    Sanctum::actingAs($driver);

    $this->putJson(route('api.v1.driver.availability.update'), ['is_online' => false])
        ->assertConflict()
        ->assertJsonPath('message', 'Finish your trip before you go offline.');

    expect($driver->driverProfile->fresh()->is_online)->toBeTrue();
})->with(['accepted', 'arrived', 'inProgress']);

it('lets the driver go offline after the trip is done', function () {
    $driver = onlineDriver();
    Ride::factory()->completed($driver)->create();

    Sanctum::actingAs($driver);

    $this->putJson(route('api.v1.driver.availability.update'), ['is_online' => false])->assertOk();
});

it('returns 403 while the application is not approved', function (string $state) {
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->for($driver)->{$state}()->create();

    Sanctum::actingAs($driver);

    $this->putJson(route('api.v1.driver.availability.update'), ['is_online' => true, 'latitude' => 7.8231, 'longitude' => 123.4402])
        ->assertForbidden()
        ->assertJsonPath('message', 'Your application is not approved yet.');

    expect($driver->driverProfile->fresh()->is_online)->toBeFalse();
})->with(['submitted', 'rejected']);

it('returns 403 for a driver who has no application', function () {
    Sanctum::actingAs(User::factory()->driver()->create());

    $this->putJson(route('api.v1.driver.availability.update'), ['is_online' => false])->assertForbidden();
});

it('returns 422 when going online without a location', function () {
    Sanctum::actingAs(approvedDriver());

    $this->putJson(route('api.v1.driver.availability.update'), ['is_online' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'longitude']);
});

it('returns 422 when the value is missing or the location is outside the Philippines', function (array $payload, array $errors) {
    Sanctum::actingAs(approvedDriver());

    $this->putJson(route('api.v1.driver.availability.update'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'no value' => [[], ['is_online']],
    'not a boolean' => [['is_online' => 'maybe'], ['is_online']],
    'far away' => [['is_online' => true, 'latitude' => 51.5, 'longitude' => -0.12], ['latitude', 'longitude']],
]);

it('returns 403 for a passenger and 401 without a token', function () {
    $this->putJson(route('api.v1.driver.availability.update'), ['is_online' => false])->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create());

    $this->putJson(route('api.v1.driver.availability.update'), ['is_online' => false])->assertForbidden();
});
