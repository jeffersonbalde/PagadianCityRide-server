<?php

use App\Models\DriverProfile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('index', function () {
    it('lists the applications that wait for review, oldest first', function () {
        $newest = DriverProfile::factory()->submitted()->create(['submitted_at' => now()->subHour()]);
        $oldest = DriverProfile::factory()->submitted()->create(['submitted_at' => now()->subDays(2)]);
        $middle = DriverProfile::factory()->submitted()->create(['submitted_at' => now()->subDay()]);
        DriverProfile::factory()->create();
        DriverProfile::factory()->approved()->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.driver-profiles.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $oldest->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonPath('data.2.id', $newest->id)
            ->assertJsonPath('data.0.status', 'submitted')
            ->assertJsonStructure(['data', 'links', 'meta']);
    });

    it('lists the applications with the status that was asked for', function () {
        DriverProfile::factory()->submitted()->create();
        $approved = DriverProfile::factory()->approved()->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.driver-profiles.index', ['status' => 'approved']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id);
    });

    it('returns 422 when the status does not exist', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.driver-profiles.index', ['status' => 'banned']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });

    it('shows 20 applications on a page', function () {
        DriverProfile::factory()->submitted()->count(21)->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.driver-profiles.index'))
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 21);
    });
});

describe('show', function () {
    it('shows an application with the phone number and the documents', function () {
        $profile = DriverProfile::factory()->submitted()->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.driver-profiles.show', $profile))
            ->assertOk()
            ->assertJsonPath('id', $profile->id)
            ->assertJsonPath('phone', $profile->user->phone)
            ->assertJsonPath('plate_number', $profile->plate_number)
            ->assertJsonCount(4, 'documents');
    });

    it('returns 404 for an application that does not exist', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.driver-profiles.show', 999))->assertNotFound();
    });
});
