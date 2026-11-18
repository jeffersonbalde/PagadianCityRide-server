<?php

use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use App\RideStatus;
use Laravel\Sanctum\Sanctum;

describe('store', function () {
    it('creates a request that waits for a driver', function () {
        $passenger = User::factory()->create();

        Sanctum::actingAs($passenger);

        $this->postJson(route('api.v1.rides.store'), rideRequestPayload())
            ->assertCreated()
            ->assertJsonPath('status', 'requested')
            ->assertJsonPath('pickup', ['latitude' => 7.8231, 'longitude' => 123.4402, 'label' => 'Balangasan'])
            ->assertJsonPath('destination.label', 'City Hall')
            ->assertJsonPath('passengers_count', 2)
            ->assertJsonPath('note', 'Red shirt, near the church')
            ->assertJsonPath('distance_meters', 1800)
            ->assertJsonMissingPath('driver')
            ->assertJsonMissingPath('passenger');

        $ride = Ride::sole();

        expect($ride->passenger_id)->toBe($passenger->id);
        expect($ride->status)->toBe(RideStatus::Requested);
        expect($ride->driver_id)->toBeNull();
    });

    it('creates a request with only what is needed', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.rides.store'), [
            'pickup' => ['latitude' => 7.8231, 'longitude' => 123.4402],
            'destination' => ['latitude' => 7.8249, 'longitude' => 123.4389, 'label' => 'City Hall'],
            'passengers_count' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('pickup.label', null)
            ->assertJsonPath('note', null)
            ->assertJsonPath('distance_meters', null);
    });

    it('returns 409 while the passenger has another ride going on', function (RideStatus $status) {
        $passenger = User::factory()->create();
        Ride::factory()->for($passenger, 'passenger')->create(['status' => $status, 'driver_id' => User::factory()->driver()]);

        Sanctum::actingAs($passenger);

        $this->postJson(route('api.v1.rides.store'), rideRequestPayload())
            ->assertConflict()
            ->assertJsonPath('message', 'You already have a ride going on.');

        $this->assertDatabaseCount('rides', 1);
    })->with([
        'waiting' => RideStatus::Requested,
        'accepted' => RideStatus::Accepted,
        'arrived' => RideStatus::Arrived,
        'on the way' => RideStatus::InProgress,
    ]);

    it('lets the passenger ask again after the earlier ride ended', function (string $state) {
        $passenger = User::factory()->create();
        Ride::factory()->for($passenger, 'passenger')->{$state}()->create();

        Sanctum::actingAs($passenger);

        $this->postJson(route('api.v1.rides.store'), rideRequestPayload())->assertCreated();
    })->with(['completed', 'cancelled', 'expired']);

    it('does not mind the rides of other passengers', function () {
        Ride::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.rides.store'), rideRequestPayload())->assertCreated();
    });

    it('returns 422 when the request is empty', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.rides.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pickup', 'destination', 'passengers_count']);

        $this->assertDatabaseCount('rides', 0);
    });

    it('returns 422 when the destination has no name', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.rides.store'), rideRequestPayload(['destination' => ['label' => '']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['destination.label' => 'Choose where you want to go.']);
    });

    it('returns 422 when the number of people is not 1 to 4', function (int $count) {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.rides.store'), rideRequestPayload(['passengers_count' => $count]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['passengers_count' => 'A tricycle carries 1 to 4 people.']);
    })->with([0, 5, -1]);

    it('returns 422 when a point is outside the Philippines', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.rides.store'), rideRequestPayload([
            'pickup' => ['latitude' => 51.5, 'longitude' => -0.12],
            'destination' => ['latitude' => 40.7, 'longitude' => -74.0],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'pickup.latitude' => 'The pick-up point must be in the Philippines.',
                'destination.latitude' => 'The destination must be in the Philippines.',
            ]);
    });

    it('returns 422 when the note is longer than 120 characters', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.rides.store'), rideRequestPayload(['note' => str_repeat('a', 121)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['note']);
    });

    it('returns 403 for a driver and 401 without a token', function () {
        $this->postJson(route('api.v1.rides.store'), rideRequestPayload())->assertUnauthorized();

        Sanctum::actingAs(User::factory()->driver()->create());

        $this->postJson(route('api.v1.rides.store'), rideRequestPayload())->assertForbidden();
    });
});

describe('index', function () {
    it('lists the finished and cancelled trips of a passenger, newest first', function () {
        $passenger = User::factory()->create();
        $first = Ride::factory()->for($passenger, 'passenger')->completed()->create();
        $second = Ride::factory()->for($passenger, 'passenger')->cancelled()->create();
        $third = Ride::factory()->for($passenger, 'passenger')->completed()->create();
        Ride::factory()->for($passenger, 'passenger')->expired()->create();
        Ride::factory()->for($passenger, 'passenger')->create();
        Ride::factory()->completed()->create();

        Sanctum::actingAs($passenger);

        $this->getJson(route('api.v1.rides.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$third->id, $second->id, $first->id])
            ->assertJsonStructure(['data', 'links', 'meta']);
    });

    it('lists only the finished trips of a driver', function () {
        $driver = User::factory()->driver()->create();
        $done = Ride::factory()->completed($driver)->create();
        Ride::factory()->accepted($driver)->create();
        Ride::factory()->completed()->create();

        Sanctum::actingAs($driver);

        $this->getJson(route('api.v1.rides.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$done->id])
            ->assertJsonPath('data.0.passenger.name', $done->passenger->name);
    });

    it('shows the rating and trip count of the driver on each trip of a passenger', function () {
        $driver = User::factory()->driver()->create();
        Ride::factory()->completed($driver)->rated(5)->create();
        $mine = Ride::factory()->completed($driver)->rated(4)->create();

        Sanctum::actingAs($mine->passenger);

        $this->getJson(route('api.v1.rides.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.driver.rating_average', 4.5)
            ->assertJsonPath('data.0.driver.completed_trips_count', 2);
    });

    it('lists the ride that is going on when the scope is ongoing', function () {
        $passenger = User::factory()->create();
        $going = Ride::factory()->for($passenger, 'passenger')->accepted()->create();
        Ride::factory()->for($passenger, 'passenger')->completed()->create();

        Sanctum::actingAs($passenger);

        $this->getJson(route('api.v1.rides.index', ['scope' => 'ongoing']))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$going->id]);
    });

    it('keeps the trips of the range that was asked for', function (string $range, int $count) {
        $this->travelTo('2026-11-11 12:00:00');
        $driver = User::factory()->driver()->create();

        Ride::factory()->completed($driver)->create(['created_at' => '2026-11-11 08:00:00']);
        Ride::factory()->completed($driver)->create(['created_at' => '2026-11-09 08:00:00']);
        Ride::factory()->completed($driver)->create(['created_at' => '2026-10-01 08:00:00']);

        Sanctum::actingAs($driver);

        $this->getJson(route('api.v1.rides.index', ['range' => $range]))
            ->assertOk()
            ->assertJsonCount($count, 'data');
    })->with([
        'today' => ['today', 1],
        'this week' => ['week', 2],
        'all' => ['all', 3],
    ]);

    it('shows 20 trips on a page', function () {
        $passenger = User::factory()->create();
        Ride::factory()->for($passenger, 'passenger')->completed()->count(21)->create();

        Sanctum::actingAs($passenger);

        $this->getJson(route('api.v1.rides.index'))
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 21);
    });

    it('returns 422 when the scope or the range does not exist', function (array $query, string $field) {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.rides.index', $query))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'scope' => [['scope' => 'future'], 'scope'],
        'range' => [['range' => 'year'], 'range'],
    ]);

    it('returns 403 for an admin', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.rides.index'))->assertForbidden();
    });
});

describe('show', function () {
    it('shows the passenger a waiting request without a driver', function () {
        $ride = Ride::factory()->create();

        Sanctum::actingAs($ride->passenger);

        $this->getJson(route('api.v1.rides.show', $ride))
            ->assertOk()
            ->assertJsonPath('id', $ride->id)
            ->assertJsonPath('status', 'requested')
            ->assertJsonMissingPath('driver');
    });

    it('shows the passenger the driver once the ride was taken', function () {
        $driver = User::factory()->driver()->create(['name' => 'Ramil Mendoza', 'phone' => '+639171234567']);
        DriverProfile::factory()->for($driver)->onlineAt(7.8240, 123.4410)->create(['tricycle_number' => '#214', 'plate_number' => 'ABC 1234']);
        Ride::factory()->completed($driver)->rated(5)->count(2)->create();
        Ride::factory()->completed($driver)->rated(4)->create();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($ride->passenger);

        $this->getJson(route('api.v1.rides.show', $ride))
            ->assertOk()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('driver.name', 'Ramil Mendoza')
            ->assertJsonPath('driver.phone', '+639171234567')
            ->assertJsonPath('driver.tricycle_number', '#214')
            ->assertJsonPath('driver.plate_number', 'ABC 1234')
            ->assertJsonPath('driver.rating_average', 4.7)
            ->assertJsonPath('driver.completed_trips_count', 3)
            ->assertJsonPath('driver.location.latitude', 7.824)
            ->assertJsonMissingPath('passenger');
    });

    it('stops showing where the driver is after the trip', function () {
        $driver = User::factory()->driver()->create();
        DriverProfile::factory()->for($driver)->onlineAt()->create();
        $ride = Ride::factory()->completed($driver)->create();

        Sanctum::actingAs($ride->passenger);

        $this->getJson(route('api.v1.rides.show', $ride))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonMissingPath('driver.location');
    });

    it('shows the driver the passenger with the phone number', function () {
        $driver = User::factory()->driver()->create();
        $passenger = User::factory()->create(['name' => 'Maria Santos', 'phone' => '+639181234567']);
        $ride = Ride::factory()->for($passenger, 'passenger')->accepted($driver)->create(['note' => 'Red shirt']);

        Sanctum::actingAs($driver);

        $this->getJson(route('api.v1.rides.show', $ride))
            ->assertOk()
            ->assertJsonPath('passenger', ['name' => 'Maria Santos', 'phone' => '+639181234567'])
            ->assertJsonPath('note', 'Red shirt')
            ->assertJsonMissingPath('driver');
    });

    it('returns 404 for a person who is not on the ride', function () {
        $ride = Ride::factory()->accepted()->create();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson(route('api.v1.rides.show', $ride))->assertNotFound();

        Sanctum::actingAs(User::factory()->driver()->create());
        $this->getJson(route('api.v1.rides.show', $ride))->assertNotFound();
    });

    it('returns 401 without a token', function () {
        $this->getJson(route('api.v1.rides.show', Ride::factory()->create()))->assertUnauthorized();
    });
});
