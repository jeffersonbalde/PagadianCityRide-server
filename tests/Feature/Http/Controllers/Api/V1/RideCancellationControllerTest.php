<?php

use App\DriverCancelReason;
use App\Models\Ride;
use App\Models\User;
use App\RideStatus;
use Laravel\Sanctum\Sanctum;

describe('as the passenger', function () {
    it('cancels the request before the trip starts', function (?string $state) {
        $this->freezeTime();
        $factory = Ride::factory();
        $ride = ($state === null ? $factory : $factory->{$state}())->create();

        Sanctum::actingAs($ride->passenger);

        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'I found another way.'])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('cancel_reason', 'I found another way.');

        $ride->refresh();

        expect($ride->status)->toBe(RideStatus::Cancelled);
        expect($ride->cancelled_at->toIso8601String())->toBe(now()->toIso8601String());
    })->with(['requested' => null, 'accepted' => 'accepted', 'arrived' => 'arrived']);

    it('cancels without giving a reason', function () {
        $ride = Ride::factory()->create();

        Sanctum::actingAs($ride->passenger);

        $this->postJson(route('api.v1.rides.cancellation.store', $ride))
            ->assertOk()
            ->assertJsonPath('cancel_reason', null);
    });

    it('returns 403 once the trip started or ended', function (string $state) {
        $ride = Ride::factory()->{$state}()->create();
        $statusBefore = $ride->status;

        Sanctum::actingAs($ride->passenger);

        $this->postJson(route('api.v1.rides.cancellation.store', $ride))
            ->assertForbidden()
            ->assertJsonPath('message', 'This ride can no longer be cancelled.');

        expect($ride->fresh()->status)->toBe($statusBefore);
    })->with(['inProgress', 'completed', 'cancelled']);

    it('returns 422 when the reason is too long', function () {
        $ride = Ride::factory()->create();

        Sanctum::actingAs($ride->passenger);

        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => str_repeat('a', 201)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    });
});

describe('as the driver', function () {
    it('gives the pickup up and sends the request back to the waiting list', function (string $state) {
        $driver = User::factory()->driver()->create();
        $ride = Ride::factory()->{$state}($driver)->create();

        Sanctum::actingAs($driver);

        $this->postJson(route('api.v1.rides.cancellation.store', $ride), [
            'reason' => 'tricycle_problem',
            'note' => 'Flat tire.',
        ])->assertOk()->assertJsonPath('status', 'requested');

        $ride->refresh();

        expect($ride->status)->toBe(RideStatus::Requested);
        expect($ride->driver_id)->toBeNull();
        expect($ride->accepted_at)->toBeNull();
        expect($ride->arrived_at)->toBeNull();

        $cancellation = $ride->driverCancellations()->sole();

        expect($cancellation->driver_id)->toBe($driver->id);
        expect($cancellation->reason)->toBe(DriverCancelReason::TricycleProblem);
        expect($cancellation->note)->toBe('Flat tire.');
    })->with(['accepted', 'arrived']);

    it('needs the words of the driver when the reason is Other', function () {
        $driver = User::factory()->driver()->create();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($driver);

        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'other'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');
        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'other', 'note' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');

        expect($ride->refresh()->status)->toBe(RideStatus::Accepted);

        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'other', 'note' => 'Family emergency.'])->assertOk();

        expect($ride->driverCancellations()->sole()->note)->toBe('Family emergency.');
    });

    it('does not need words for a reason from the list', function () {
        $driver = User::factory()->driver()->create();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($driver);

        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'too_far'])->assertOk();
    });

    it('tells the passenger on the ride itself why the driver gave it up', function () {
        $driver = User::factory()->driver()->create();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($driver);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'other', 'note' => 'My tricycle ran out of fuel.'])->assertOk();

        Sanctum::actingAs($ride->passenger);
        $this->getJson(route('api.v1.rides.current.show'))
            ->assertOk()
            ->assertJsonPath('status', 'requested')
            ->assertJsonPath('driver_cancellation.reason', 'My tricycle ran out of fuel.')
            ->assertJsonPath('driver_cancellation.cancelled_at', fn (string $at) => $at !== '');
    });

    it('shows the passenger only the last driver who gave the ride up, and not the driver', function () {
        $first = User::factory()->driver()->create();
        $ride = Ride::factory()->accepted($first)->create();

        Sanctum::actingAs($first);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'too_far'])->assertOk();

        $second = User::factory()->driver()->create();
        $ride->refresh()->update(['driver_id' => $second->id, 'status' => RideStatus::Accepted, 'accepted_at' => now()]);

        Sanctum::actingAs($second);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'tricycle_problem'])->assertOk()
            ->assertJsonMissingPath('driver_cancellation');

        Sanctum::actingAs($ride->passenger);
        $this->getJson(route('api.v1.rides.current.show'))->assertJsonPath('driver_cancellation.reason', 'Tricycle problem');
    });

    it('does not show a driver cancellation once another driver took the ride', function () {
        $first = User::factory()->driver()->create();
        $ride = Ride::factory()->accepted($first)->create();

        Sanctum::actingAs($first);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'too_far'])->assertOk();

        $ride->refresh()->update(['driver_id' => User::factory()->driver()->create()->id, 'status' => RideStatus::Accepted, 'accepted_at' => now()]);

        Sanctum::actingAs($ride->passenger);
        $this->getJson(route('api.v1.rides.current.show'))
            ->assertOk()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonMissingPath('driver_cancellation');
    });

    it('lets the passenger see that the driver is gone', function () {
        $driver = User::factory()->driver()->create();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($driver);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'too_far'])->assertOk();

        Sanctum::actingAs($ride->passenger);
        $this->getJson(route('api.v1.rides.show', $ride))
            ->assertOk()
            ->assertJsonPath('status', 'requested')
            ->assertJsonMissingPath('driver');
    });

    it('puts the passenger back on the map of the driver who gave the pickup up, who can take them again', function () {
        $driver = onlineDriver();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($driver);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'too_far'])->assertOk();

        $this->getJson(route('api.v1.driver.waiting-rides.index'))->assertOk()->assertJsonPath('*.id', [$ride->id]);
        $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))->assertCreated()->assertJsonPath('status', 'accepted');

        expect($ride->refresh()->driver_id)->toBe($driver->id);
    });

    it('lets the driver take the same passenger again every time they gave the pickup up', function () {
        $driver = onlineDriver();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($driver);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'too_far'])->assertOk();
        $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))->assertCreated();
        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'tricycle_problem'])->assertOk();

        $this->getJson(route('api.v1.driver.waiting-rides.index'))->assertOk()->assertJsonPath('*.id', [$ride->id]);
        $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))->assertCreated()->assertJsonPath('status', 'accepted');
    });

    it('lets another driver take the request that was given up', function () {
        $first = onlineDriver();
        $ride = Ride::factory()->accepted($first)->create();

        Sanctum::actingAs($first);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'too_far'])->assertOk();

        Sanctum::actingAs(onlineDriver());
        $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))
            ->assertCreated()
            ->assertJsonPath('status', 'accepted');
    });

    it('returns 422 when the reason is missing or not on the list', function (array $payload) {
        $driver = User::factory()->driver()->create();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($driver);

        $this->postJson(route('api.v1.rides.cancellation.store', $ride), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        expect($ride->fresh()->status)->toBe(RideStatus::Accepted);
    })->with([
        'no reason' => [[]],
        'not on the list' => [['reason' => 'bored']],
    ]);

    it('accepts every reason on the list', function (DriverCancelReason $reason) {
        $driver = User::factory()->driver()->create();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($driver);

        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => $reason->value, 'note' => 'A few words.'])->assertOk();
    })->with(DriverCancelReason::cases());

    it('returns 403 once the trip started', function () {
        $driver = User::factory()->driver()->create();
        $ride = Ride::factory()->inProgress($driver)->create();

        Sanctum::actingAs($driver);

        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'too_far'])->assertForbidden();

        expect($ride->fresh()->status)->toBe(RideStatus::InProgress);
    });
});

it('returns 404 for a person who is not on the ride', function () {
    $ride = Ride::factory()->accepted()->create();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson(route('api.v1.rides.cancellation.store', $ride))->assertNotFound();

    Sanctum::actingAs(User::factory()->driver()->create());
    $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'too_far'])->assertNotFound();

    expect($ride->fresh()->status)->toBe(RideStatus::Accepted);
});

it('returns 401 without a token', function () {
    $this->postJson(route('api.v1.rides.cancellation.store', Ride::factory()->create()))->assertUnauthorized();
});
