<?php

use App\DriverStatus;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

describe('view', function () {
    it('lets the owner and an admin view, and nobody else', function () {
        $profile = DriverProfile::factory()->create();

        expect(Gate::forUser($profile->user)->allows('view', $profile))->toBeTrue();
        expect(Gate::forUser(User::factory()->admin()->create())->allows('view', $profile))->toBeTrue();
        expect(Gate::forUser(User::factory()->driver()->create())->allows('view', $profile))->toBeFalse();
        expect(Gate::forUser(User::factory()->unverified()->create())->allows('view', $profile))->toBeFalse();
    });
});

describe('update', function () {
    it('lets the owner change a draft or an application that was sent back', function (DriverStatus $status) {
        $profile = DriverProfile::factory()->create(['status' => $status]);

        expect(Gate::forUser($profile->user)->allows('update', $profile))->toBeTrue();
    })->with([
        'draft' => DriverStatus::Draft,
        'rejected' => DriverStatus::Rejected,
    ]);

    it('does not let the owner change an application that was sent or approved', function (DriverStatus $status) {
        $profile = DriverProfile::factory()->create(['status' => $status]);

        $response = Gate::forUser($profile->user)->inspect('update', $profile);

        expect($response->allowed())->toBeFalse();
        expect($response->message())->toBe('The application can no longer be changed.');
    })->with([
        'submitted' => DriverStatus::Submitted,
        'approved' => DriverStatus::Approved,
    ]);

    it('does not let another person change it, and says it does not exist', function () {
        $profile = DriverProfile::factory()->create();

        $response = Gate::forUser(User::factory()->driver()->create())->inspect('update', $profile);

        expect($response->allowed())->toBeFalse();
        expect($response->status())->toBe(404);
    });
});

describe('review', function () {
    it('lets an admin review an application that waits for review', function () {
        $profile = DriverProfile::factory()->create(['status' => DriverStatus::Submitted]);

        expect(Gate::forUser(User::factory()->admin()->create())->allows('review', $profile))->toBeTrue();
    });

    it('does not let an admin review an application in any other status', function (DriverStatus $status) {
        $profile = DriverProfile::factory()->create(['status' => $status]);

        $response = Gate::forUser(User::factory()->admin()->create())->inspect('review', $profile);

        expect($response->allowed())->toBeFalse();
        expect($response->message())->toBe('This application is not waiting for review.');
    })->with([
        'draft' => DriverStatus::Draft,
        'approved' => DriverStatus::Approved,
        'rejected' => DriverStatus::Rejected,
    ]);

    it('does not let the driver review their own application', function () {
        $profile = DriverProfile::factory()->create(['status' => DriverStatus::Submitted]);

        expect(Gate::forUser($profile->user)->allows('review', $profile))->toBeFalse();
    });
});
