<?php

use App\AlertType;
use App\Models\Ride;
use App\Models\RideReport;
use App\Models\User;
use App\Notifications\Alert;
use App\ReportStatus;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

describe('index', function () {
    it('lists the open reports first-come first-served, with who and which ride', function () {
        $this->freezeTime();
        $later = RideReport::factory()->create(['created_at' => now()->subHour()]);
        $first = RideReport::factory()->create(['created_at' => now()->subDay()]);
        RideReport::factory()->resolved()->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.ride-reports.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$first->id, $later->id])
            ->assertJsonPath('data.0.category', 'safety')
            ->assertJsonPath('data.0.reporter.role', 'passenger')
            ->assertJsonPath('data.0.reported.role', 'driver')
            ->assertJsonPath('data.0.ride.destination_label', 'City Hall')
            ->assertJsonStructure(['data', 'links', 'meta']);
    });

    it('lists the reports of another status when asked', function (string $status) {
        $open = RideReport::factory()->create();
        $closed = RideReport::factory()->{$status}()->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.ride-reports.index', ['status' => $status]))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$closed->id]);
    })->with(['resolved', 'dismissed']);

    it('returns 422 for a status that does not exist', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.ride-reports.index', ['status' => 'lost']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });

    it('is for admins only', function () {
        $this->getJson(route('api.v1.admin.ride-reports.index'))->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson(route('api.v1.admin.ride-reports.index'))->assertForbidden();

        Sanctum::actingAs(User::factory()->driver()->create());
        $this->getJson(route('api.v1.admin.ride-reports.index'))->assertForbidden();
    });
});

describe('show', function () {
    it('shows one report with the phone numbers of both people', function () {
        $report = RideReport::factory()->create(['details' => 'He drove too fast.']);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.ride-reports.show', $report))
            ->assertOk()
            ->assertJsonPath('id', $report->id)
            ->assertJsonPath('details', 'He drove too fast.')
            ->assertJsonPath('reporter.phone', $report->reporter->phone)
            ->assertJsonPath('reported.phone', $report->reportedUser->phone)
            ->assertJsonPath('admin_note', null);
    });

    it('returns 404 for a report that does not exist and 403 for a passenger', function () {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson(route('api.v1.admin.ride-reports.show', 9999))->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson(route('api.v1.admin.ride-reports.show', RideReport::factory()->create()))->assertForbidden();
    });
});

describe('resolution', function () {
    it('closes the report with a note and tells the reporter it was looked at', function (string $status) {
        Notification::fake();
        $this->freezeTime();
        $admin = User::factory()->admin()->create();
        $report = RideReport::factory()->create();

        Sanctum::actingAs($admin);

        $this->postJson(route('api.v1.admin.ride-reports.resolution.store', $report), ['status' => $status, 'note' => 'Called the driver.'])
            ->assertOk()
            ->assertJsonPath('status', $status)
            ->assertJsonPath('admin_note', 'Called the driver.')
            ->assertJsonPath('handled_at', now()->toIso8601String());

        $report->refresh();

        expect($report->status->value)->toBe($status);
        expect($report->handled_by)->toBe($admin->id);

        Notification::assertSentTo($report->reporter, Alert::class, fn (Alert $alert) => $alert->type === AlertType::ReportHandled);
        Notification::assertNotSentTo($report->reportedUser, Alert::class);
    })->with(['resolved', 'dismissed']);

    it('keeps the note of the admin away from the reporter', function () {
        $ride = Ride::factory()->completed()->create();
        $report = RideReport::factory()->create(['ride_id' => $ride->id, 'reporter_id' => $ride->passenger_id, 'reported_user_id' => $ride->driver_id]);

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson(route('api.v1.admin.ride-reports.resolution.store', $report), ['status' => 'resolved', 'note' => 'Secret note.'])->assertOk();

        Sanctum::actingAs($ride->passenger);
        $response = $this->getJson(route('api.v1.rides.show', $ride))->assertOk()->assertJsonPath('my_report.status', 'resolved');

        expect($response->getContent())->not->toContain('Secret note.');
        expect($ride->passenger->notifications()->sole()->data['type'])->toBe('report.handled');
    });

    it('returns 409 when the report was already handled', function () {
        $report = RideReport::factory()->resolved()->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('api.v1.admin.ride-reports.resolution.store', $report), ['status' => 'dismissed', 'note' => 'Again.'])
            ->assertConflict()
            ->assertJsonPath('message', 'This report was already handled.');

        expect($report->fresh()->status)->toBe(ReportStatus::Resolved);
    });

    it('returns 422 without a note or with a status that cannot be chosen', function (array $payload, string $field) {
        $report = RideReport::factory()->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(route('api.v1.admin.ride-reports.resolution.store', $report), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        expect($report->fresh()->status)->toBe(ReportStatus::Open);
    })->with([
        'no note' => [['status' => 'resolved'], 'note'],
        'open again' => [['status' => 'open', 'note' => 'x'], 'status'],
        'no status' => [['note' => 'x'], 'status'],
    ]);

    it('is for admins only', function () {
        $report = RideReport::factory()->create();

        $this->postJson(route('api.v1.admin.ride-reports.resolution.store', $report), ['status' => 'resolved', 'note' => 'x'])->assertUnauthorized();

        Sanctum::actingAs($report->reporter);
        $this->postJson(route('api.v1.admin.ride-reports.resolution.store', $report), ['status' => 'resolved', 'note' => 'x'])->assertForbidden();

        expect($report->fresh()->status)->toBe(ReportStatus::Open);
    });
});
