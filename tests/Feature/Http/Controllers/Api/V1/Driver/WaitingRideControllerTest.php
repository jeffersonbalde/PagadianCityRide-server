<?php

use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\RideDriverCancellation;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lists the waiting passengers near the driver, nearest first', function () {
    $driver = onlineDriver();
    $far = Ride::factory()->pickupAt(7.8400, 123.4402)->create();
    $near = Ride::factory()->pickupAt(7.8240, 123.4402)->create();

    Sanctum::actingAs($driver);

    $this->getJson(route('api.v1.driver.waiting-rides.index'))
        ->assertOk()
        ->assertJsonPath('*.id', [$near->id, $far->id])
        ->assertJsonPath('0.distance_to_pickup_meters', fn (float $meters) => $meters > 50 && $meters < 150)
        ->assertJsonPath('1.distance_to_pickup_meters', fn (float $meters) => $meters > 1500 && $meters < 2200);
});

it('shows what the driver needs to decide, without the phone number', function () {
    $passenger = User::factory()->create(['name' => 'Maria Santos', 'phone' => '+639181234567']);
    $ride = Ride::factory()->for($passenger, 'passenger')->create([
        'passengers_count' => 3,
        'note' => 'Red shirt',
        'destination_label' => 'Public market',
        'destination_latitude' => 7.8311,
        'destination_longitude' => 123.4501,
        'distance_meters' => 2400,
    ]);

    Sanctum::actingAs(onlineDriver());

    $response = $this->getJson(route('api.v1.driver.waiting-rides.index'))
        ->assertOk()
        ->assertJsonPath('0.id', $ride->id)
        ->assertJsonPath('0.passenger_name', 'Maria Santos')
        ->assertJsonPath('0.passengers_count', 3)
        ->assertJsonPath('0.pickup.label', 'Balangasan')
        ->assertJsonPath('0.destination_label', 'Public market')
        ->assertJsonPath('0.destination.label', 'Public market')
        ->assertJsonPath('0.destination.latitude', 7.8311)
        ->assertJsonPath('0.destination.longitude', 123.4501)
        ->assertJsonPath('0.note', 'Red shirt')
        ->assertJsonPath('0.distance_meters', 2400);

    expect($response->getContent())->not->toContain('+639181234567');
});

it('counts the seconds the passenger has waited', function () {
    $this->freezeTime();
    $ride = Ride::factory()->create(['created_at' => now()->subSeconds(90)]);

    Sanctum::actingAs(onlineDriver());

    $this->getJson(route('api.v1.driver.waiting-rides.index'))
        ->assertOk()
        ->assertJsonPath('0.waiting_seconds', 90)
        ->assertJsonPath('0.requested_at', $ride->created_at->toIso8601String());
});

it('lists a passenger however far away while there is no distance limit', function () {
    $far = Ride::factory()->pickupAt(7.9500, 123.4402)->create();

    Sanctum::actingAs(onlineDriver());

    $this->getJson(route('api.v1.driver.waiting-rides.index'))
        ->assertOk()
        ->assertJsonPath('*.id', [$far->id])
        ->assertJsonPath('0.distance_to_pickup_meters', fn (float $meters) => $meters > 13000);
});

it('leaves out requests beyond the distance limit when one is set', function () {
    config(['rides.search_radius_meters' => 3000]);
    $near = Ride::factory()->create();
    Ride::factory()->pickupAt(7.9000, 123.4402)->create();

    Sanctum::actingAs(onlineDriver());

    $this->getJson(route('api.v1.driver.waiting-rides.index'))
        ->assertOk()
        ->assertJsonPath('*.id', [$near->id]);
});

it('leaves out requests that are already taken, cancelled or expired', function () {
    $driver = onlineDriver();
    $visible = Ride::factory()->create();
    Ride::factory()->accepted()->create();
    Ride::factory()->cancelled()->create();
    Ride::factory()->expired()->create();
    Ride::factory()->completed()->create();

    Sanctum::actingAs($driver);

    $this->getJson(route('api.v1.driver.waiting-rides.index'))
        ->assertOk()
        ->assertJsonPath('*.id', [$visible->id]);
});

it('lists the request the driver gave up again, while the passenger still waits', function () {
    $driver = onlineDriver();
    $gaveUp = Ride::factory()->create();
    RideDriverCancellation::factory()->for($gaveUp)->count(3)->create(['driver_id' => $driver->id]);

    Sanctum::actingAs($driver);

    $this->getJson(route('api.v1.driver.waiting-rides.index'))
        ->assertOk()
        ->assertJsonPath('*.id', [$gaveUp->id]);
});

it('still lists a request that another driver gave up', function () {
    $ride = Ride::factory()->create();
    RideDriverCancellation::factory()->for($ride)->create();

    Sanctum::actingAs(onlineDriver());

    $this->getJson(route('api.v1.driver.waiting-rides.index'))->assertOk()->assertJsonCount(1);
});

it('shows at most 20 requests', function () {
    Ride::factory()->count(21)->create();

    Sanctum::actingAs(onlineDriver());

    $this->getJson(route('api.v1.driver.waiting-rides.index'))->assertOk()->assertJsonCount(20);
});

it('lists the requests around the last location of the driver', function () {
    $nearPort = Ride::factory()->pickupAt(7.8326, 123.4311)->create();
    Ride::factory()->create();

    Sanctum::actingAs(onlineDriver(7.8326, 123.4311));

    $this->getJson(route('api.v1.driver.waiting-rides.index'))
        ->assertOk()
        ->assertJsonPath('0.id', $nearPort->id);
});

it('returns 409 when the driver is offline or has no location', function (array $attributes) {
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->for($driver)->approved()->create($attributes);

    Sanctum::actingAs($driver);

    $this->getJson(route('api.v1.driver.waiting-rides.index'))
        ->assertConflict()
        ->assertJsonPath('message', 'Go online to see the waiting passengers.');
})->with([
    'offline' => [['is_online' => false, 'latitude' => 7.8231, 'longitude' => 123.4402]],
    'no location' => [['is_online' => true, 'latitude' => null, 'longitude' => null]],
]);

it('returns 403 for a passenger and 401 without a token', function () {
    $this->getJson(route('api.v1.driver.waiting-rides.index'))->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.driver.waiting-rides.index'))->assertForbidden();
});
