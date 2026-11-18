<?php

use App\Models\Ride;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 401 without a token', function () {
    $this->getJson(route('api.v1.rides.current.show'))->assertUnauthorized();
});

it('returns 204 when the passenger has no ride going on', function () {
    $passenger = User::factory()->create();
    Ride::factory()->for($passenger, 'passenger')->completed()->create();
    Ride::factory()->for($passenger, 'passenger')->cancelled()->create();

    Sanctum::actingAs($passenger);

    $this->getJson(route('api.v1.rides.current.show'))->assertNoContent();
});

it('returns the ride that is going on for the passenger', function () {
    $passenger = User::factory()->create();
    Ride::factory()->for($passenger, 'passenger')->completed()->create();
    $going = Ride::factory()->for($passenger, 'passenger')->arrived()->create();

    Sanctum::actingAs($passenger);

    $this->getJson(route('api.v1.rides.current.show'))
        ->assertOk()
        ->assertJsonPath('id', $going->id)
        ->assertJsonPath('status', 'arrived')
        ->assertJsonStructure(['driver' => ['name', 'phone']]);
});

it('returns the ride that is going on for the driver', function () {
    $driver = User::factory()->driver()->create();
    $going = Ride::factory()->inProgress($driver)->create();

    Sanctum::actingAs($driver);

    $this->getJson(route('api.v1.rides.current.show'))
        ->assertOk()
        ->assertJsonPath('id', $going->id)
        ->assertJsonPath('status', 'in_progress')
        ->assertJsonStructure(['passenger' => ['name', 'phone']]);
});

it('returns 204 for a driver whose last trip is done', function () {
    $driver = User::factory()->driver()->create();
    Ride::factory()->completed($driver)->create();

    Sanctum::actingAs($driver);

    $this->getJson(route('api.v1.rides.current.show'))->assertNoContent();
});

it('does not return the ride of someone else', function () {
    Ride::factory()->accepted()->create();

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.rides.current.show'))->assertNoContent();
});
