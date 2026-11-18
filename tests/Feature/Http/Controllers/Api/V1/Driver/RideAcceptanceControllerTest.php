<?php

use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\RideDriverCancellation;
use App\Models\User;
use App\RideStatus;
use Laravel\Sanctum\Sanctum;

it('lets an online driver pick up a waiting passenger', function () {
    $this->freezeTime();
    $passenger = User::factory()->create(['name' => 'Maria Santos', 'phone' => '+639181234567']);
    $ride = Ride::factory()->for($passenger, 'passenger')->create();
    $driver = onlineDriver();

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))
        ->assertCreated()
        ->assertJsonPath('status', 'accepted')
        ->assertJsonPath('accepted_at', now()->toIso8601String())
        ->assertJsonPath('passenger', ['name' => 'Maria Santos', 'phone' => '+639181234567'])
        ->assertJsonMissingPath('driver');

    $ride->refresh();

    expect($ride->status)->toBe(RideStatus::Accepted);
    expect($ride->driver_id)->toBe($driver->id);
});

it('returns 409 when another driver was faster', function () {
    $ride = Ride::factory()->create();
    $first = onlineDriver();
    $second = onlineDriver();

    Sanctum::actingAs($first);
    $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))->assertCreated();

    Sanctum::actingAs($second);
    $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))
        ->assertConflict()
        ->assertJsonPath('message', 'This passenger was already picked up.');

    expect($ride->fresh()->driver_id)->toBe($first->id);
});

it('returns 409 when the request is no longer waiting', function (string $state) {
    $ride = Ride::factory()->{$state}()->create();

    Sanctum::actingAs(onlineDriver());

    $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))
        ->assertConflict()
        ->assertJsonPath('message', 'This passenger was already picked up.');
})->with(['cancelled', 'expired', 'completed']);

it('returns 409 when the driver is still on a trip', function () {
    $driver = onlineDriver();
    Ride::factory()->inProgress($driver)->create();
    $ride = Ride::factory()->create();

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))
        ->assertConflict()
        ->assertJsonPath('message', 'Finish your trip first.');

    expect($ride->fresh()->status)->toBe(RideStatus::Requested);
});

it('returns 409 when the driver is offline', function () {
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->for($driver)->approved()->create(['is_online' => false]);
    $ride = Ride::factory()->create();

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))
        ->assertConflict()
        ->assertJsonPath('message', 'Go online to take passengers.');

    expect($ride->fresh()->status)->toBe(RideStatus::Requested);
});

it('returns 403 while the application is not approved', function (string $state) {
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->for($driver)->{$state}()->create();
    $ride = Ride::factory()->create();

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))
        ->assertForbidden()
        ->assertJsonPath('message', 'Your application is not approved yet.');

    expect($ride->fresh()->status)->toBe(RideStatus::Requested);
})->with(['submitted', 'rejected']);

it('lets a driver who gave the pickup up take it again', function () {
    $driver = onlineDriver();
    $ride = Ride::factory()->create();
    RideDriverCancellation::factory()->for($ride)->create(['driver_id' => $driver->id]);

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))
        ->assertCreated()
        ->assertJsonPath('status', 'accepted');

    expect($ride->fresh()->driver_id)->toBe($driver->id);
});

it('returns 403 when the driver asks for his own ride as a passenger', function () {
    $driver = onlineDriver();
    $ride = Ride::factory()->for($driver, 'passenger')->create();

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))
        ->assertForbidden()
        ->assertJsonPath('message', 'You cannot pick up your own request.');
});

it('returns 404 when the ride does not exist', function () {
    Sanctum::actingAs(onlineDriver());

    $this->postJson(route('api.v1.driver.rides.acceptance.store', 999999))->assertNotFound();
});

it('returns 403 for a passenger and 401 without a token', function () {
    $ride = Ride::factory()->create();

    $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))->assertUnauthorized();

    Sanctum::actingAs($ride->passenger);

    $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))->assertForbidden();
});
