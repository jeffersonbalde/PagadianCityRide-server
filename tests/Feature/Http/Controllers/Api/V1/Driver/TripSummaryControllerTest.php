<?php

use App\Models\Ride;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('counts the trips and the people carried in each range', function (string $range, int $trips, int $passengers) {
    $this->travelTo('2026-11-11 12:00:00');
    $driver = User::factory()->driver()->create();

    Ride::factory()->completed($driver)->create(['passengers_count' => 2, 'created_at' => '2026-11-11 08:00:00']);
    Ride::factory()->completed($driver)->create(['passengers_count' => 1, 'created_at' => '2026-11-09 08:00:00']);
    Ride::factory()->completed($driver)->create(['passengers_count' => 4, 'created_at' => '2026-10-01 08:00:00']);

    Sanctum::actingAs($driver);

    $this->getJson(route('api.v1.driver.trip-summary.show', ['range' => $range]))
        ->assertOk()
        ->assertExactJson(['range' => $range, 'trips' => $trips, 'passengers' => $passengers]);
})->with([
    'today' => ['today', 1, 2],
    'this week' => ['week', 2, 3],
    'all' => ['all', 3, 7],
]);

it('shows every trip when no range is given', function () {
    $driver = User::factory()->driver()->create();
    Ride::factory()->completed($driver)->count(2)->create(['created_at' => now()->subMonths(3)]);

    Sanctum::actingAs($driver);

    $this->getJson(route('api.v1.driver.trip-summary.show'))
        ->assertOk()
        ->assertExactJson(['range' => 'all', 'trips' => 2, 'passengers' => 2]);
});

it('counts only finished trips of this driver', function () {
    $driver = User::factory()->driver()->create();
    Ride::factory()->completed($driver)->create();
    Ride::factory()->inProgress($driver)->create();
    Ride::factory()->accepted($driver)->create();
    Ride::factory()->completed()->create();

    Sanctum::actingAs($driver);

    $this->getJson(route('api.v1.driver.trip-summary.show'))
        ->assertOk()
        ->assertJsonPath('trips', 1);
});

it('shows zeros for a driver without trips', function () {
    Sanctum::actingAs(User::factory()->driver()->create());

    $this->getJson(route('api.v1.driver.trip-summary.show'))
        ->assertOk()
        ->assertExactJson(['range' => 'all', 'trips' => 0, 'passengers' => 0]);
});

it('returns 422 when the range does not exist', function () {
    Sanctum::actingAs(User::factory()->driver()->create());

    $this->getJson(route('api.v1.driver.trip-summary.show', ['range' => 'year']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['range']);
});

it('returns 403 for a passenger and 401 without a token', function () {
    $this->getJson(route('api.v1.driver.trip-summary.show'))->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.driver.trip-summary.show'))->assertForbidden();
});
