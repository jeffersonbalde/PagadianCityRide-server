<?php

use App\Models\DriverProfile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function nearbyUrl(array $query = []): string
{
    return route('api.v1.drivers.nearby.index', array_merge(['latitude' => 7.8231, 'longitude' => 123.4402], $query));
}

it('returns 401 without a token', function () {
    $this->getJson(nearbyUrl())->assertUnauthorized();
});

it('returns 403 for a driver', function () {
    Sanctum::actingAs(User::factory()->driver()->create());

    $this->getJson(nearbyUrl())->assertForbidden();
});

it('counts the online drivers near the passenger and shows only where they are', function () {
    onlineDriver(7.8240, 123.4410);
    onlineDriver(7.8300, 123.4500);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(nearbyUrl())
        ->assertOk()
        ->assertJsonPath('count', 2)
        ->assertJsonPath('nearest_meters', fn (int $meters) => $meters < 200)
        ->assertJsonPath('drivers', [
            ['latitude' => 7.824, 'longitude' => 123.441],
            ['latitude' => 7.83, 'longitude' => 123.45],
        ]);
});

it('lists the nearest driver first', function () {
    onlineDriver(7.8400, 123.4402);
    onlineDriver(7.8240, 123.4402);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(nearbyUrl())
        ->assertOk()
        ->assertJsonPath('drivers.0.latitude', 7.824)
        ->assertJsonPath('drivers.1.latitude', 7.84);
});

it('shows a driver however far away while there is no distance limit', function () {
    onlineDriver(7.9500, 123.4402);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(nearbyUrl())
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('nearest_meters', fn (int $meters) => $meters > 13000);
});

it('leaves out drivers beyond the distance limit when one is set', function () {
    config(['rides.search_radius_meters' => 3000]);
    onlineDriver(7.8240, 123.4402);
    onlineDriver(7.9500, 123.4402);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(nearbyUrl())->assertOk()->assertJsonPath('count', 1);
});

it('leaves out drivers who are offline, stale or not approved', function () {
    onlineDriver();

    $offline = onlineDriver();
    $offline->driverProfile->update(['is_online' => false]);

    $stale = onlineDriver();
    $stale->driverProfile->update(['located_at' => now()->subMinutes(4)]);

    $notApproved = onlineDriver();
    $notApproved->driverProfile->update(['status' => 'submitted']);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(nearbyUrl())->assertOk()->assertJsonPath('count', 1);
});

it('keeps a driver whose location is a little old', function () {
    $driver = onlineDriver();
    $driver->driverProfile->update(['located_at' => now()->subMinutes(2)]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(nearbyUrl())->assertOk()->assertJsonPath('count', 1);
});

it('returns zero and no nearest distance when nobody is near', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(nearbyUrl())
        ->assertOk()
        ->assertExactJson(['count' => 0, 'nearest_meters' => null, 'drivers' => []]);
});

it('returns 422 when the point is missing or outside the Philippines', function (array $query) {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.drivers.nearby.index', $query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'longitude']);
})->with([
    'missing' => [[]],
    'outside the Philippines' => [['latitude' => 51.5, 'longitude' => -0.12]],
]);

it('does not expose the identity of a driver', function () {
    $driver = onlineDriver();

    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson(nearbyUrl())->assertOk();

    expect($response->getContent())
        ->not->toContain($driver->phone)
        ->not->toContain($driver->name)
        ->not->toContain((string) DriverProfile::sole()->plate_number);
});
