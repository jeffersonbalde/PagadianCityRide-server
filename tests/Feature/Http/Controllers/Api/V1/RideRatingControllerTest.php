<?php

use App\Models\Ride;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('saves the stars and the review of the passenger', function () {
    $this->freezeTime();
    $ride = Ride::factory()->completed()->create();

    Sanctum::actingAs($ride->passenger);

    $this->postJson(route('api.v1.rides.rating.store', $ride), ['rating' => 5, 'review' => 'Very kind driver.'])
        ->assertCreated()
        ->assertJsonPath('rating', 5)
        ->assertJsonPath('review', 'Very kind driver.');

    $ride->refresh();

    expect($ride->rating)->toBe(5);
    expect($ride->review)->toBe('Very kind driver.');
    expect($ride->rated_at->toIso8601String())->toBe(now()->toIso8601String());
});

it('saves stars without a review', function () {
    $ride = Ride::factory()->completed()->create();

    Sanctum::actingAs($ride->passenger);

    $this->postJson(route('api.v1.rides.rating.store', $ride), ['rating' => 3])
        ->assertCreated()
        ->assertJsonPath('review', null);
});

it('adds the stars to the average of the driver', function () {
    $driver = User::factory()->driver()->create();
    Ride::factory()->completed($driver)->rated(5)->create();
    $ride = Ride::factory()->completed($driver)->create();

    Sanctum::actingAs($ride->passenger);

    $this->postJson(route('api.v1.rides.rating.store', $ride), ['rating' => 4])
        ->assertCreated()
        ->assertJsonPath('driver.rating_average', 4.5)
        ->assertJsonPath('driver.completed_trips_count', 2);
});

it('returns 403 when the trip is not done yet', function (string $state) {
    $ride = Ride::factory()->{$state}()->create();

    Sanctum::actingAs($ride->passenger);

    $this->postJson(route('api.v1.rides.rating.store', $ride), ['rating' => 5])
        ->assertForbidden()
        ->assertJsonPath('message', 'You can rate the driver after the trip.');

    expect($ride->fresh()->rating)->toBeNull();
})->with(['accepted', 'inProgress', 'cancelled']);

it('returns 403 when the passenger already rated the trip', function () {
    $ride = Ride::factory()->completed()->rated(5)->create();

    Sanctum::actingAs($ride->passenger);

    $this->postJson(route('api.v1.rides.rating.store', $ride), ['rating' => 1])
        ->assertForbidden()
        ->assertJsonPath('message', 'You already rated this trip.');

    expect($ride->fresh()->rating)->toBe(5);
});

it('returns 422 when the stars are missing or out of range', function (array $payload, string $message) {
    $ride = Ride::factory()->completed()->create();

    Sanctum::actingAs($ride->passenger);

    $this->postJson(route('api.v1.rides.rating.store', $ride), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rating' => $message]);

    expect($ride->fresh()->rating)->toBeNull();
})->with([
    'missing' => [[], 'Tap the stars to rate the driver.'],
    'zero' => [['rating' => 0], 'The rating is from 1 to 5 stars.'],
    'six' => [['rating' => 6], 'The rating is from 1 to 5 stars.'],
]);

it('returns 422 when the review is longer than 500 characters', function () {
    $ride = Ride::factory()->completed()->create();

    Sanctum::actingAs($ride->passenger);

    $this->postJson(route('api.v1.rides.rating.store', $ride), ['rating' => 5, 'review' => str_repeat('a', 501)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['review']);
});

it('returns 404 for someone who is not the passenger of the ride', function () {
    $ride = Ride::factory()->completed()->create();

    Sanctum::actingAs(User::factory()->create());

    $this->postJson(route('api.v1.rides.rating.store', $ride), ['rating' => 5])->assertNotFound();

    expect($ride->fresh()->rating)->toBeNull();
});

it('returns 403 for a driver and 401 without a token', function () {
    $ride = Ride::factory()->completed()->create();

    $this->postJson(route('api.v1.rides.rating.store', $ride), ['rating' => 5])->assertUnauthorized();

    Sanctum::actingAs($ride->driver);

    $this->postJson(route('api.v1.rides.rating.store', $ride), ['rating' => 5])->assertForbidden();
});
