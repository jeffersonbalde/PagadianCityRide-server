<?php

use App\Models\Ride;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('gives the rating, the trips and the reviews of the driver, newest first', function () {
    $driver = User::factory()->driver()->create();
    $maria = User::factory()->create(['name' => 'Maria Santos']);
    $jose = User::factory()->create(['name' => 'Jose Cruz']);

    $mariaRide = Ride::factory()->completed($driver)->create(['passenger_id' => $maria->id, 'rating' => 5, 'review' => 'Safe and on time.', 'completed_at' => now()->subDays(2)]);
    $joseRide = Ride::factory()->completed($driver)->create(['passenger_id' => $jose->id, 'rating' => 4, 'review' => 'A little late.', 'completed_at' => now()->subDay()]);
    Ride::factory()->completed($driver)->create(['rating' => null, 'review' => null]);

    Sanctum::actingAs($driver);

    $this->getJson(route('api.v1.driver.reviews.index'))
        ->assertOk()
        ->assertJsonPath('rating_average', 4.5)
        ->assertJsonPath('completed_trips_count', 3)
        ->assertJsonCount(2, 'reviews')
        ->assertJsonPath('reviews.0.ride_id', $joseRide->id)
        ->assertJsonPath('reviews.0.reviewer', 'Jose')
        ->assertJsonPath('reviews.0.rating', 4)
        ->assertJsonPath('reviews.1.ride_id', $mariaRide->id)
        ->assertJsonPath('reviews.1.review', 'Safe and on time.');
});

it('lists stars that came without words', function () {
    $driver = User::factory()->driver()->create();
    Ride::factory()->completed($driver)->create(['rating' => 5, 'review' => null]);

    Sanctum::actingAs($driver);

    $this->getJson(route('api.v1.driver.reviews.index'))
        ->assertOk()
        ->assertJsonPath('reviews.0.rating', 5)
        ->assertJsonPath('reviews.0.review', null);
});

it('keeps the reviews of other drivers out', function () {
    $driver = User::factory()->driver()->create();
    Ride::factory()->completed(User::factory()->driver()->create())->create(['rating' => 1, 'review' => 'Not mine']);

    Sanctum::actingAs($driver);

    $this->getJson(route('api.v1.driver.reviews.index'))
        ->assertOk()
        ->assertJsonPath('rating_average', null)
        ->assertJsonPath('completed_trips_count', 0)
        ->assertJsonCount(0, 'reviews');
});

it('is for drivers only', function () {
    $this->getJson(route('api.v1.driver.reviews.index'))->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.driver.reviews.index'))->assertForbidden();
});

it('lets the driver open the profile of the passenger who wrote a review', function () {
    $driver = User::factory()->driver()->create();
    $ride = Ride::factory()->completed($driver)->create(['rating' => 5, 'review' => 'Nice']);

    Sanctum::actingAs($driver);

    $rideId = $this->getJson(route('api.v1.driver.reviews.index'))->json('reviews.0.ride_id');

    $this->getJson(route('api.v1.driver.rides.passenger.show', $rideId))
        ->assertOk()
        ->assertJsonPath('name', $ride->passenger->name);
});
