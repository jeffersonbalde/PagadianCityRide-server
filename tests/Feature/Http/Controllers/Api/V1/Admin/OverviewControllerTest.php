<?php

use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\RideReport;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('show', function () {
    it('counts the drivers online now, the passengers waiting and the trips of today', function () {
        DriverProfile::factory()->onlineAt()->count(2)->create();
        DriverProfile::factory()->onlineAt()->create(['located_at' => now()->subMinutes(10)]);
        DriverProfile::factory()->approved()->create();

        Ride::factory()->count(3)->create();
        $driver = User::factory()->driver()->create();
        Ride::factory()->completed($driver)->count(2)->create();
        Ride::factory()->completed($driver)->create(['created_at' => now()->subDays(2)]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.overview.show'))
            ->assertOk()
            ->assertJsonPath('drivers_online', 2)
            ->assertJsonPath('passengers_waiting', 3)
            ->assertJsonPath('trips_today', 2);
    });

    it('averages the wait of the rides that were picked up today', function () {
        $driver = User::factory()->driver()->create();

        Ride::factory()->accepted($driver)->create(['created_at' => now()->subMinutes(10), 'accepted_at' => now()->subMinutes(8)]);
        Ride::factory()->accepted($driver)->create(['created_at' => now()->subMinutes(10), 'accepted_at' => now()->subMinutes(4)]);
        Ride::factory()->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.overview.show'))
            ->assertOk()
            ->assertJsonPath('average_wait_minutes', 4);
    });

    it('has no average wait when nobody was picked up today', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.overview.show'))
            ->assertOk()
            ->assertJsonPath('average_wait_minutes', null)
            ->assertJsonPath('recent_trips', []);
    });

    it('counts what waits for the admins', function () {
        DriverProfile::factory()->submitted()->count(2)->create();
        DriverProfile::factory()->approved()->create();
        RideReport::factory()->count(3)->create();
        RideReport::factory()->resolved()->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.overview.show'))
            ->assertOk()
            ->assertJsonPath('pending_applications', 2)
            ->assertJsonPath('open_reports', 3);
    });

    it('lists the last ten trips, newest first, with the driver when there is one', function () {
        $profile = DriverProfile::factory()->approved()->create();
        $done = Ride::factory()->completed($profile->user)->create(['created_at' => now()->subMinutes(5), 'accepted_at' => now()->subMinutes(3)]);
        $waiting = Ride::factory()->create(['created_at' => now()->subMinute()]);
        Ride::factory()->count(10)->create(['created_at' => now()->subHours(3)]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson(route('api.v1.admin.overview.show'))
            ->assertOk()
            ->assertJsonCount(10, 'recent_trips')
            ->assertJsonPath('recent_trips.0.id', $waiting->id)
            ->assertJsonPath('recent_trips.0.driver_name', null)
            ->assertJsonPath('recent_trips.0.wait_minutes', null)
            ->assertJsonPath('recent_trips.0.status', 'requested')
            ->assertJsonPath('recent_trips.1.id', $done->id)
            ->assertJsonPath('recent_trips.1.driver_name', $profile->user->name)
            ->assertJsonPath('recent_trips.1.tricycle_number', $profile->tricycle_number)
            ->assertJsonPath('recent_trips.1.wait_minutes', 2)
            ->assertJsonPath('recent_trips.1.status', 'completed');

        expect($response->json('recent_trips.1.pickup_label'))->toBe($done->pickup_label);
    });

    it('is for admins only', function () {
        $this->getJson(route('api.v1.admin.overview.show'))->assertUnauthorized();

        Sanctum::actingAs(User::factory()->driver()->create());

        $this->getJson(route('api.v1.admin.overview.show'))->assertForbidden();
    });
});
