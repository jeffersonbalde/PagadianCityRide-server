<?php

use App\Models\Ride;
use App\Models\User;
use App\RideStatus;
use Laravel\Sanctum\Sanctum;

it('finishes the trip when the passenger is dropped off', function () {
    $this->freezeTime();
    $driver = User::factory()->driver()->create();
    $ride = Ride::factory()->inProgress($driver)->create();

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.rides.completion.store', $ride))
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('completed_at', now()->toIso8601String());

    expect($ride->fresh()->status)->toBe(RideStatus::Completed);
});

it('frees the driver for the next passenger', function () {
    $driver = onlineDriver();
    $ride = Ride::factory()->inProgress($driver)->create();
    $next = Ride::factory()->create();

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.rides.acceptance.store', $next))->assertConflict();

    $this->postJson(route('api.v1.driver.rides.completion.store', $ride))->assertOk();

    $this->postJson(route('api.v1.driver.rides.acceptance.store', $next))->assertCreated();
});

it('lets the passenger rate the driver after the trip', function () {
    $driver = User::factory()->driver()->create();
    $ride = Ride::factory()->inProgress($driver)->create();

    Sanctum::actingAs($driver);
    $this->postJson(route('api.v1.driver.rides.completion.store', $ride))->assertOk();

    Sanctum::actingAs($ride->passenger);
    $this->postJson(route('api.v1.rides.rating.store', $ride), ['rating' => 5])->assertCreated();
});

it('returns 403 when the trip has not started or is already over', function (string $state) {
    $driver = User::factory()->driver()->create();
    $ride = Ride::factory()->{$state}($driver)->create();
    $statusBefore = $ride->status;

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.driver.rides.completion.store', $ride))
        ->assertForbidden()
        ->assertJsonPath('message', 'The trip has not started.');

    expect($ride->fresh()->status)->toBe($statusBefore);
})->with(['accepted', 'arrived', 'completed']);

it('returns 404 for a driver who is not on the ride', function () {
    $ride = Ride::factory()->inProgress()->create();

    Sanctum::actingAs(User::factory()->driver()->create());

    $this->postJson(route('api.v1.driver.rides.completion.store', $ride))->assertNotFound();

    expect($ride->fresh()->status)->toBe(RideStatus::InProgress);
});

it('returns 403 for a passenger and 401 without a token', function () {
    $ride = Ride::factory()->inProgress()->create();

    $this->postJson(route('api.v1.driver.rides.completion.store', $ride))->assertUnauthorized();

    Sanctum::actingAs($ride->passenger);

    $this->postJson(route('api.v1.driver.rides.completion.store', $ride))->assertForbidden();

    expect($ride->fresh()->status)->toBe(RideStatus::InProgress);
});
