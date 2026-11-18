<?php

use App\Models\Ride;
use App\Models\User;
use App\RideStatus;
use Laravel\Sanctum\Sanctum;

it('starts the trip when the passenger is on the tricycle', function () {
    $this->freezeTime();
    $ride = Ride::factory()->arrived()->create();

    Sanctum::actingAs($ride->passenger);

    $this->postJson(route('api.v1.rides.start.store', $ride))
        ->assertOk()
        ->assertJsonPath('status', 'in_progress')
        ->assertJsonPath('started_at', now()->toIso8601String());

    expect($ride->fresh()->status)->toBe(RideStatus::InProgress);
});

it('lets the driver start the trip too', function () {
    $driver = User::factory()->driver()->create();
    $ride = Ride::factory()->arrived($driver)->create();

    Sanctum::actingAs($driver);

    $this->postJson(route('api.v1.rides.start.store', $ride))
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');
});

it('returns 403 when the tricycle has not arrived yet or the trip is over', function (?string $state) {
    $factory = Ride::factory();
    $ride = ($state === null ? $factory : $factory->{$state}())->create();
    $statusBefore = $ride->status;

    Sanctum::actingAs($ride->passenger);

    $this->postJson(route('api.v1.rides.start.store', $ride))
        ->assertForbidden()
        ->assertJsonPath('message', 'The tricycle has not arrived yet.');

    expect($ride->fresh()->status)->toBe($statusBefore);
})->with(['waiting' => null, 'accepted' => 'accepted', 'on the way' => 'inProgress', 'completed' => 'completed', 'cancelled' => 'cancelled']);

it('returns 404 for a person who is not on the ride', function () {
    $ride = Ride::factory()->arrived()->create();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson(route('api.v1.rides.start.store', $ride))->assertNotFound();

    Sanctum::actingAs(User::factory()->driver()->create());
    $this->postJson(route('api.v1.rides.start.store', $ride))->assertNotFound();

    expect($ride->fresh()->status)->toBe(RideStatus::Arrived);
});

it('returns 401 without a token', function () {
    $this->postJson(route('api.v1.rides.start.store', Ride::factory()->arrived()->create()))->assertUnauthorized();
});
