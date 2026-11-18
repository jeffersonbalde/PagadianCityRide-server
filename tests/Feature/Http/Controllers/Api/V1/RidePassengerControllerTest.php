<?php

use App\Models\Ride;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('shows the driver who the passenger is, with the contact number and the trips', function () {
    $passenger = User::factory()->create(['name' => 'Maria Santos', 'phone' => '+639181234567', 'phone_verified_at' => now(), 'created_at' => now()->subMonths(3)]);
    $ride = Ride::factory()->for($passenger, 'passenger')->completed()->create();
    Ride::factory()->for($passenger, 'passenger')->completed($ride->driver)->create();
    Ride::factory()->for($passenger, 'passenger')->completed()->create();
    Ride::factory()->for($passenger, 'passenger')->accepted($ride->driver)->create();

    Sanctum::actingAs($ride->driver);

    $this->getJson(route('api.v1.driver.rides.passenger.show', $ride))
        ->assertOk()
        ->assertJsonPath('name', 'Maria Santos')
        ->assertJsonPath('phone', '+639181234567')
        ->assertJsonPath('phone_verified', true)
        ->assertJsonPath('member_since', $passenger->created_at->toIso8601String())
        ->assertJsonPath('trips_completed_count', 3)
        ->assertJsonPath('trips_together', 2);
});

it('says when the phone was not verified', function () {
    $ride = Ride::factory()->accepted()->create();
    $ride->passenger->forceFill(['phone_verified_at' => null])->save();

    Sanctum::actingAs($ride->driver);

    $this->getJson(route('api.v1.driver.rides.passenger.show', $ride))->assertOk()->assertJsonPath('phone_verified', false);
});

it('is only for the driver of the ride', function () {
    $ride = Ride::factory()->accepted()->create();

    Sanctum::actingAs(User::factory()->driver()->create());
    $this->getJson(route('api.v1.driver.rides.passenger.show', $ride))->assertNotFound();

    Sanctum::actingAs($ride->passenger);
    $this->getJson(route('api.v1.driver.rides.passenger.show', $ride))->assertForbidden();

    $this->app['auth']->forgetGuards();
    $this->getJson(route('api.v1.driver.rides.passenger.show', $ride))->assertUnauthorized();
});
