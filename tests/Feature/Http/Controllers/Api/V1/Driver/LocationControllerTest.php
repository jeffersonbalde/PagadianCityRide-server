<?php

use App\Events\DriverLocationUpdated;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

it('saves where the driver is now', function () {
    $this->freezeTime();
    $driver = onlineDriver();
    $this->travel(2)->minutes();

    Sanctum::actingAs($driver);

    $this->putJson(route('api.v1.driver.location.update'), ['latitude' => 7.8300, 'longitude' => 123.4500])
        ->assertOk()
        ->assertExactJson([
            'is_online' => true,
            'latitude' => 7.83,
            'longitude' => 123.45,
            'located_at' => now()->toIso8601String(),
        ]);

    expect($driver->driverProfile->fresh()->located_at->toIso8601String())->toBe(now()->toIso8601String());
});

it('keeps a driver on the map by refreshing the location', function () {
    $this->freezeTime();
    $passenger = User::factory()->create();
    $driver = onlineDriver();
    $this->travel(5)->minutes();

    Sanctum::actingAs($passenger);
    $this->getJson(route('api.v1.drivers.nearby.index', ['latitude' => 7.8231, 'longitude' => 123.4402]))
        ->assertJsonPath('count', 0);

    Sanctum::actingAs($driver);
    $this->putJson(route('api.v1.driver.location.update'), ['latitude' => 7.8231, 'longitude' => 123.4402])->assertOk();

    Sanctum::actingAs($passenger);
    $this->getJson(route('api.v1.drivers.nearby.index', ['latitude' => 7.8231, 'longitude' => 123.4402]))
        ->assertJsonPath('count', 1);
});

it('returns 409 when the driver is offline', function () {
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->for($driver)->approved()->create(['is_online' => false]);

    Sanctum::actingAs($driver);

    $this->putJson(route('api.v1.driver.location.update'), ['latitude' => 7.83, 'longitude' => 123.45])
        ->assertConflict()
        ->assertJsonPath('message', 'Go online first.');
});

it('returns 422 when the location is missing or outside the Philippines', function (array $payload) {
    Sanctum::actingAs(onlineDriver());

    $this->putJson(route('api.v1.driver.location.update'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'longitude']);
})->with([
    'missing' => [[]],
    'far away' => [['latitude' => 51.5, 'longitude' => -0.12]],
]);

it('returns 403 for a passenger and 401 without a token', function () {
    $this->putJson(route('api.v1.driver.location.update'), ['latitude' => 7.83, 'longitude' => 123.45])->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create());

    $this->putJson(route('api.v1.driver.location.update'), ['latitude' => 7.83, 'longitude' => 123.45])->assertForbidden();
});

describe('the passenger on the map', function () {
    it('hears the new location of the driver during a ride', function (string $state) {
        Event::fake([DriverLocationUpdated::class]);
        $driver = onlineDriver();
        $ride = Ride::factory()->{$state}($driver)->create();

        Sanctum::actingAs($driver);

        $this->putJson(route('api.v1.driver.location.update'), ['latitude' => 7.8300, 'longitude' => 123.4500])->assertOk();

        Event::assertDispatched(DriverLocationUpdated::class, fn (DriverLocationUpdated $event) => $event->rideId === $ride->id
            && $event->passengerId === $ride->passenger_id
            && $event->latitude === 7.83
            && $event->longitude === 123.45);
    })->with(['accepted', 'arrived', 'inProgress']);

    it('hears nothing when the driver has no ride or the ride is over', function () {
        Event::fake([DriverLocationUpdated::class]);
        $driver = onlineDriver();
        Ride::factory()->completed($driver)->create();
        Ride::factory()->cancelled()->create();

        Sanctum::actingAs($driver);

        $this->putJson(route('api.v1.driver.location.update'), ['latitude' => 7.83, 'longitude' => 123.45])->assertOk();

        Event::assertNotDispatched(DriverLocationUpdated::class);
    });

    it('is sent live on the private channel of the passenger only', function () {
        $event = new DriverLocationUpdated(5, 9, 7.83, 123.45);

        expect(collect($event->broadcastOn())->map->name->all())->toBe(['private-App.Models.User.9']);
        expect($event->broadcastAs())->toBe('driver.location');
        expect($event->broadcastWith())->toBe(['ride_id' => 5, 'latitude' => 7.83, 'longitude' => 123.45]);
    });

    it('goes to the queue', function () {
        Queue::fake();

        DriverLocationUpdated::dispatch(5, 9, 7.83, 123.45);

        Queue::assertPushed(BroadcastEvent::class, fn (BroadcastEvent $job) => $job->event instanceof DriverLocationUpdated);
    });
});
