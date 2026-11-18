<?php

use App\Models\Ride;
use App\Models\User;
use App\RideStatus;
use Laravel\Sanctum\Sanctum;

it('tells the passenger that the tricycle is at the pickup point', function () {
    $this->freezeTime();
    $driver = User::factory()->driver()->create();
    $ride = Ride::factory()->accepted($driver)->create();

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.rides.arrival.store', $ride))
        ->assertOk()
        ->assertJsonPath('status', 'arrived')
        ->assertJsonPath('arrived_at', now()->toIso8601String());

    expect($ride->fresh()->status)->toBe(RideStatus::Arrived);

    Sanctum::actingAs($ride->passenger);

    $this->getJson(route('api.v1.rides.current.show'))->assertOk()->assertJsonPath('status', 'arrived');
});

it('returns 403 when the ride is not waiting for the driver to arrive', function (string $state) {
    $driver = User::factory()->driver()->create();
    $ride = Ride::factory()->{$state}($driver)->create();
    $statusBefore = $ride->status;

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.rides.arrival.store', $ride))
        ->assertForbidden()
        ->assertJsonPath('message', 'The ride is not waiting for the driver to arrive.');

    expect($ride->fresh()->status)->toBe($statusBefore);
})->with(['arrived', 'inProgress', 'completed']);

it('returns 404 for a driver who is not on the ride', function () {
    $ride = Ride::factory()->accepted()->create();

    Sanctum::actingAs(User::factory()->driver()->create());

    $this->postJson(route('api.v1.driver.rides.arrival.store', $ride))->assertNotFound();

    expect($ride->fresh()->status)->toBe(RideStatus::Accepted);
});

it('returns 404 for a request nobody picked up', function () {
    $ride = Ride::factory()->create();

    Sanctum::actingAs(User::factory()->driver()->create());

    $this->postJson(route('api.v1.driver.rides.arrival.store', $ride))->assertNotFound();
});

it('returns 403 for a passenger and 401 without a token', function () {
    $ride = Ride::factory()->accepted()->create();

    $this->postJson(route('api.v1.driver.rides.arrival.store', $ride))->assertUnauthorized();

    Sanctum::actingAs($ride->passenger);

    $this->postJson(route('api.v1.driver.rides.arrival.store', $ride))->assertForbidden();
});
