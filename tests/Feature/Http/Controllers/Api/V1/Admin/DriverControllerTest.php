<?php

use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('index', function () {
    it('lists only the approved drivers, by name', function () {
        $bea = DriverProfile::factory()->approved()->create(['user_id' => User::factory()->driver()->state(['name' => 'Bea Cruz'])]);
        $ana = DriverProfile::factory()->approved()->create(['user_id' => User::factory()->driver()->state(['name' => 'Ana Lim'])]);
        DriverProfile::factory()->submitted()->create();
        DriverProfile::factory()->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.drivers.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $ana->user_id)
            ->assertJsonPath('data.1.id', $bea->user_id)
            ->assertJsonPath('data.0.tricycle_number', $ana->tricycle_number)
            ->assertJsonPath('data.0.plate_number', $ana->plate_number)
            ->assertJsonPath('meta.total', 2);
    });

    it('shows who is online now', function () {
        $online = DriverProfile::factory()->onlineAt()->create(['user_id' => User::factory()->driver()->state(['name' => 'A Online'])]);
        $stale = DriverProfile::factory()->onlineAt()->create(['user_id' => User::factory()->driver()->state(['name' => 'B Stale']), 'located_at' => now()->subMinutes(10)]);
        $offline = DriverProfile::factory()->approved()->create(['user_id' => User::factory()->driver()->state(['name' => 'C Offline'])]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.drivers.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $online->user_id)
            ->assertJsonPath('data.0.is_online', true)
            ->assertJsonPath('data.1.id', $stale->user_id)
            ->assertJsonPath('data.1.is_online', false)
            ->assertJsonPath('data.2.id', $offline->user_id)
            ->assertJsonPath('data.2.is_online', false);
    });

    it('shows the rating and the finished trips of a driver', function () {
        $profile = DriverProfile::factory()->approved()->create();
        Ride::factory()->completed($profile->user)->rated(5)->create();
        Ride::factory()->completed($profile->user)->rated(4)->create();
        Ride::factory()->accepted($profile->user)->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.drivers.index'))
            ->assertOk()
            ->assertJsonPath('data.0.rating_average', 4.5)
            ->assertJsonPath('data.0.completed_trips_count', 2);
    });

    it('has no rating for a driver who was not rated yet', function () {
        DriverProfile::factory()->approved()->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.drivers.index'))
            ->assertOk()
            ->assertJsonPath('data.0.rating_average', null)
            ->assertJsonPath('data.0.completed_trips_count', 0);
    });

    it('is for admins only', function () {
        $this->getJson(route('api.v1.admin.drivers.index'))->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.admin.drivers.index'))->assertForbidden();
    });
});
