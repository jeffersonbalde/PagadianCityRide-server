<?php

use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

describe('view', function () {
    it('lets the passenger and the driver view, and nobody else', function () {
        $ride = Ride::factory()->accepted()->create();

        expect(Gate::forUser($ride->passenger)->allows('view', $ride))->toBeTrue();
        expect(Gate::forUser($ride->driver)->allows('view', $ride))->toBeTrue();
        expect(Gate::forUser(User::factory()->create())->allows('view', $ride))->toBeFalse();
        expect(Gate::forUser(User::factory()->driver()->create())->allows('view', $ride))->toBeFalse();
        expect(Gate::forUser(User::factory()->admin()->create())->allows('view', $ride))->toBeFalse();
    });

    it('hides the ride from a stranger as if it did not exist', function () {
        $ride = Ride::factory()->create();

        $response = Gate::forUser(User::factory()->create())->inspect('view', $ride);

        expect($response->allowed())->toBeFalse();
        expect($response->status())->toBe(404);
    });
});

describe('cancel', function () {
    it('lets a participant cancel until the trip starts', function (string $state) {
        $ride = Ride::factory()->{$state}()->create();

        expect(Gate::forUser($ride->passenger)->allows('cancel', $ride))->toBeTrue();
    })->with(['accepted', 'arrived']);

    it('lets the passenger cancel a waiting request', function () {
        $ride = Ride::factory()->create();

        expect(Gate::forUser($ride->passenger)->allows('cancel', $ride))->toBeTrue();
    });

    it('does not allow it once the trip started or ended', function (string $state) {
        $ride = Ride::factory()->{$state}()->create();

        $response = Gate::forUser($ride->passenger)->inspect('cancel', $ride);

        expect($response->allowed())->toBeFalse();
        expect($response->message())->toBe('This ride can no longer be cancelled.');
    })->with(['inProgress', 'completed', 'cancelled', 'expired']);

    it('gives a stranger a 404', function () {
        $ride = Ride::factory()->accepted()->create();

        expect(Gate::forUser(User::factory()->create())->inspect('cancel', $ride)->status())->toBe(404);
    });
});

describe('start', function () {
    it('lets both people on the ride start it once the driver arrived', function () {
        $ride = Ride::factory()->arrived()->create();

        expect(Gate::forUser($ride->passenger)->allows('start', $ride))->toBeTrue();
        expect(Gate::forUser($ride->driver)->allows('start', $ride))->toBeTrue();
    });

    it('does not allow it before the driver arrived', function () {
        $ride = Ride::factory()->accepted()->create();

        expect(Gate::forUser($ride->passenger)->inspect('start', $ride)->message())->toBe('The tricycle has not arrived yet.');
    });

    it('gives a stranger a 404', function () {
        $ride = Ride::factory()->arrived()->create();

        expect(Gate::forUser(User::factory()->driver()->create())->inspect('start', $ride)->status())->toBe(404);
    });
});

describe('arrive and complete', function () {
    it('are for the driver of the ride only', function () {
        $ride = Ride::factory()->accepted()->create();
        $inProgress = Ride::factory()->inProgress()->create();

        expect(Gate::forUser($ride->driver)->allows('arrive', $ride))->toBeTrue();
        expect(Gate::forUser($ride->passenger)->inspect('arrive', $ride)->status())->toBe(404);
        expect(Gate::forUser(User::factory()->driver()->create())->inspect('arrive', $ride)->status())->toBe(404);

        expect(Gate::forUser($inProgress->driver)->allows('complete', $inProgress))->toBeTrue();
        expect(Gate::forUser($inProgress->passenger)->inspect('complete', $inProgress)->status())->toBe(404);
    });

    it('wait for the right moment of the trip', function () {
        $waiting = Ride::factory()->accepted()->create();
        $arrived = Ride::factory()->arrived()->create();

        expect(Gate::forUser($waiting->driver)->allows('complete', $waiting))->toBeFalse();
        expect(Gate::forUser($arrived->driver)->allows('arrive', $arrived))->toBeFalse();
    });

    it('do not work on a request nobody picked up', function () {
        $ride = Ride::factory()->create();

        expect(Gate::forUser(User::factory()->driver()->create())->inspect('arrive', $ride)->status())->toBe(404);
    });
});

describe('rate', function () {
    it('lets the passenger rate a finished trip once', function () {
        $ride = Ride::factory()->completed()->create();

        expect(Gate::forUser($ride->passenger)->allows('rate', $ride))->toBeTrue();

        $ride->update(['rating' => 5]);

        $response = Gate::forUser($ride->passenger)->inspect('rate', $ride);

        expect($response->allowed())->toBeFalse();
        expect($response->message())->toBe('You already rated this trip.');
    });

    it('does not allow it before the trip is done', function () {
        $ride = Ride::factory()->inProgress()->create();

        expect(Gate::forUser($ride->passenger)->inspect('rate', $ride)->message())->toBe('You can rate the driver after the trip.');
    });

    it('is not for the driver or a stranger', function () {
        $ride = Ride::factory()->completed()->create();

        expect(Gate::forUser($ride->driver)->inspect('rate', $ride)->status())->toBe(404);
        expect(Gate::forUser(User::factory()->create())->inspect('rate', $ride)->status())->toBe(404);
    });
});
