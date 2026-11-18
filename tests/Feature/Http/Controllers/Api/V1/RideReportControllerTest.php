<?php

use App\Models\Ride;
use App\Models\RideReport;
use App\Models\User;
use App\ReportCategory;
use App\ReportStatus;
use Laravel\Sanctum\Sanctum;

describe('store', function () {
    it('lets the passenger report the driver', function (string $state) {
        $ride = Ride::factory()->{$state}()->create();

        Sanctum::actingAs($ride->passenger);

        $this->postJson(route('api.v1.rides.report.store', $ride), ['category' => 'behavior', 'details' => 'He was rude.'])
            ->assertCreated()
            ->assertJsonPath('category', 'behavior')
            ->assertJsonPath('details', 'He was rude.')
            ->assertJsonPath('status', 'open');

        $report = RideReport::sole();

        expect($report->reporter_id)->toBe($ride->passenger_id);
        expect($report->reported_user_id)->toBe($ride->driver_id);
        expect($report->category)->toBe(ReportCategory::Behavior);
        expect($report->status)->toBe(ReportStatus::Open);
    })->with(['accepted', 'arrived', 'inProgress', 'completed']);

    it('lets the driver report the passenger', function () {
        $ride = Ride::factory()->completed()->create();

        Sanctum::actingAs($ride->driver);

        $this->postJson(route('api.v1.rides.report.store', $ride), ['category' => 'no_show'])->assertCreated();

        $report = RideReport::sole();

        expect($report->reporter_id)->toBe($ride->driver_id);
        expect($report->reported_user_id)->toBe($ride->passenger_id);
        expect($report->details)->toBeNull();
    });

    it('lets the passenger report a driver who was cancelled on later', function () {
        $ride = Ride::factory()->accepted()->cancelled()->create();

        Sanctum::actingAs($ride->passenger);

        $this->postJson(route('api.v1.rides.report.store', $ride), ['category' => 'other'])->assertCreated();
    });

    it('returns 409 when the person already reported the ride, and lets the other person report too', function () {
        $ride = Ride::factory()->completed()->create();

        Sanctum::actingAs($ride->passenger);
        $this->postJson(route('api.v1.rides.report.store', $ride), ['category' => 'safety'])->assertCreated();
        $this->postJson(route('api.v1.rides.report.store', $ride), ['category' => 'route'])
            ->assertConflict()
            ->assertJsonPath('message', 'You already reported this ride.');

        Sanctum::actingAs($ride->driver);
        $this->postJson(route('api.v1.rides.report.store', $ride), ['category' => 'no_show'])->assertCreated();

        $this->assertDatabaseCount('ride_reports', 2);
    });

    it('returns 403 when nobody drove the ride', function (string $state) {
        $ride = Ride::factory()->{$state}()->create();

        Sanctum::actingAs($ride->passenger);

        $this->postJson(route('api.v1.rides.report.store', $ride), ['category' => 'other'])
            ->assertForbidden()
            ->assertJsonPath('message', 'There is nobody to report on this ride.');

        $this->assertDatabaseCount('ride_reports', 0);
    })->with(['expired', 'cancelled']);

    it('returns 404 for a person who is not on the ride', function () {
        $ride = Ride::factory()->completed()->create();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson(route('api.v1.rides.report.store', $ride), ['category' => 'other'])->assertNotFound();

        Sanctum::actingAs(User::factory()->driver()->create());
        $this->postJson(route('api.v1.rides.report.store', $ride), ['category' => 'other'])->assertNotFound();

        $this->assertDatabaseCount('ride_reports', 0);
    });

    it('returns 422 when the category is missing or unknown, or the details are too long', function (array $payload, string $field) {
        $ride = Ride::factory()->completed()->create();

        Sanctum::actingAs($ride->passenger);

        $this->postJson(route('api.v1.rides.report.store', $ride), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'missing' => [[], 'category'],
        'unknown' => [['category' => 'cash'], 'category'],
        'long' => [['category' => 'other', 'details' => str_repeat('a', 501)], 'details'],
    ]);

    it('returns 401 without a token and 403 for an admin', function () {
        $ride = Ride::factory()->completed()->create();

        $this->postJson(route('api.v1.rides.report.store', $ride), ['category' => 'other'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('api.v1.rides.report.store', $ride), ['category' => 'other'])->assertForbidden();
    });
});

describe('my_report on the ride', function () {
    it('shows the person their own report and never the report of the other person', function () {
        $ride = Ride::factory()->completed()->create();
        RideReport::factory()->create(['ride_id' => $ride->id, 'reporter_id' => $ride->driver_id, 'reported_user_id' => $ride->passenger_id, 'category' => ReportCategory::NoShow]);

        Sanctum::actingAs($ride->passenger);
        $this->getJson(route('api.v1.rides.show', $ride))->assertOk()->assertJsonPath('my_report', null);

        $this->postJson(route('api.v1.rides.report.store', $ride), ['category' => 'safety'])->assertCreated();
        $this->getJson(route('api.v1.rides.show', $ride))
            ->assertOk()
            ->assertJsonPath('my_report.category', 'safety')
            ->assertJsonMissingPath('my_report.admin_note');

        Sanctum::actingAs($ride->driver);
        $this->getJson(route('api.v1.rides.show', $ride))->assertOk()->assertJsonPath('my_report.category', 'no_show');
    });

    it('is on the ride lists too', function () {
        $ride = Ride::factory()->completed()->create();
        RideReport::factory()->create(['ride_id' => $ride->id, 'reporter_id' => $ride->passenger_id, 'reported_user_id' => $ride->driver_id]);

        Sanctum::actingAs($ride->passenger);

        $this->getJson(route('api.v1.rides.index'))->assertOk()->assertJsonPath('data.0.my_report.status', 'open');
    });
});
