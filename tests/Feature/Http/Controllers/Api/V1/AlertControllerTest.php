<?php

use App\AlertType;
use App\Models\Ride;
use App\Models\User;
use App\Notifications\Alert;
use Laravel\Sanctum\Sanctum;

it('returns 401 without a token', function () {
    $this->getJson(route('api.v1.alerts.index'))->assertUnauthorized();
});

it('returns 403 for an admin', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson(route('api.v1.alerts.index'))->assertForbidden();
});

it('lists the alerts of the signed in person, newest first', function () {
    $this->freezeTime();
    $passenger = User::factory()->create();
    $ride = Ride::factory()->accepted()->for($passenger, 'passenger')->create();

    $passenger->notify(new Alert(AlertType::RideAccepted, $ride));
    $this->travel(5)->minutes();
    $passenger->notify(new Alert(AlertType::DriverArrived, $ride));
    User::factory()->create()->notify(new Alert(AlertType::RideExpired));

    Sanctum::actingAs($passenger);

    $this->getJson(route('api.v1.alerts.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.type', 'ride.driver-arrived')
        ->assertJsonPath('data.0.title', 'Your driver is here')
        ->assertJsonPath('data.0.body', 'Look for the tricycle at your pickup point.')
        ->assertJsonPath('data.0.ride_id', $ride->id)
        ->assertJsonPath('data.0.read_at', null)
        ->assertJsonPath('data.0.created_at', now()->toIso8601String())
        ->assertJsonPath('data.1.type', 'ride.accepted')
        ->assertJsonStructure(['data' => [['id', 'type', 'title', 'body', 'ride_id', 'read_at', 'created_at']], 'links', 'meta', 'unread_count']);
});

it('counts only the unread alerts for the badge', function () {
    $driver = User::factory()->driver()->create();
    $driver->notify(new Alert(AlertType::DriverApproved));
    $driver->notify(new Alert(AlertType::RideCancelled));
    $driver->notifications()->first()->markAsRead();

    Sanctum::actingAs($driver);

    $this->getJson(route('api.v1.alerts.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('unread_count', 1);
});

it('shows 20 alerts on a page', function () {
    $passenger = User::factory()->create();

    foreach (range(1, 21) as $ignored) {
        $passenger->notify(new Alert(AlertType::RideExpired));
    }

    Sanctum::actingAs($passenger);

    $this->getJson(route('api.v1.alerts.index'))
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 21)
        ->assertJsonPath('unread_count', 21);
});

it('returns an empty list for a person without alerts', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.alerts.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('unread_count', 0);
});

it('fills the list while a ride goes on', function () {
    $this->freezeTime();
    $ride = Ride::factory()->create();
    $driver = onlineDriver();

    Sanctum::actingAs($driver);
    $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))->assertCreated();
    $this->travel(1)->minute();
    $this->postJson(route('api.v1.driver.rides.arrival.store', $ride))->assertOk();

    Sanctum::actingAs($ride->passenger);

    $this->getJson(route('api.v1.alerts.index'))
        ->assertOk()
        ->assertJsonPath('data.*.type', ['ride.driver-arrived', 'ride.accepted'])
        ->assertJsonPath('unread_count', 2);
});
