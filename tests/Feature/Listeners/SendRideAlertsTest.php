<?php

use App\AlertType;
use App\Models\Ride;
use App\Models\User;
use App\Notifications\Alert;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

function assertAlertSent(User $user, AlertType $type): void
{
    Notification::assertSentTo($user, Alert::class, fn (Alert $alert) => $alert->type === $type);
}

beforeEach(function () {
    Notification::fake();
});

describe('a new request', function () {
    it('tells the closest free drivers and not the passenger', function () {
        config(['rides.notify_nearest_drivers' => 2]);

        $third = onlineDriver(7.8260, 123.4402);
        $first = onlineDriver(7.8240, 123.4402);
        $second = onlineDriver(7.8250, 123.4402);
        $passenger = User::factory()->create();

        Sanctum::actingAs($passenger);
        $this->postJson(route('api.v1.rides.store'), rideRequestPayload())->assertCreated();

        assertAlertSent($first, AlertType::NewRideNearby);
        assertAlertSent($second, AlertType::NewRideNearby);
        Notification::assertNotSentTo($third, Alert::class);
        Notification::assertNotSentTo($passenger, Alert::class);
    });

    it('tells a free driver however far away while there is no distance limit', function () {
        $far = onlineDriver(7.9500, 123.4402);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson(route('api.v1.rides.store'), rideRequestPayload())->assertCreated();

        assertAlertSent($far, AlertType::NewRideNearby);
    });

    it('skips drivers beyond the distance limit when one is set', function () {
        config(['rides.search_radius_meters' => 3000]);
        $far = onlineDriver(7.9000, 123.4402);
        $near = onlineDriver();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson(route('api.v1.rides.store'), rideRequestPayload())->assertCreated();

        assertAlertSent($near, AlertType::NewRideNearby);
        Notification::assertNotSentTo($far, Alert::class);
    });

    it('skips drivers who are offline, busy or not approved', function () {
        $offline = onlineDriver();
        $offline->driverProfile->update(['is_online' => false]);

        $busy = onlineDriver();
        Ride::factory()->accepted($busy)->create();

        $notApproved = onlineDriver();
        $notApproved->driverProfile->update(['status' => 'submitted']);

        $free = onlineDriver();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson(route('api.v1.rides.store'), rideRequestPayload())->assertCreated();

        assertAlertSent($free, AlertType::NewRideNearby);

        foreach ([$offline, $busy, $notApproved] as $driver) {
            Notification::assertNotSentTo($driver, Alert::class);
        }
    });

    it('does not keep the message in the alert list', function () {
        $driver = onlineDriver();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson(route('api.v1.rides.store'), rideRequestPayload())->assertCreated();

        Notification::assertSentTo($driver, Alert::class, fn (Alert $alert, array $channels) => ! in_array('database', $channels, true));
    });
});

describe('a ride that moves on', function () {
    it('tells the passenger when a driver accepts', function () {
        $ride = Ride::factory()->create();
        $driver = onlineDriver();

        Sanctum::actingAs($driver);
        $this->postJson(route('api.v1.driver.rides.acceptance.store', $ride))->assertCreated();

        assertAlertSent($ride->passenger, AlertType::RideAccepted);
        Notification::assertNotSentTo($driver, Alert::class);
    });

    it('tells the passenger when the driver arrives', function () {
        $driver = User::factory()->driver()->create();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($driver);
        $this->postJson(route('api.v1.driver.rides.arrival.store', $ride))->assertOk();

        assertAlertSent($ride->passenger, AlertType::DriverArrived);
    });

    it('says nothing when the trip starts', function () {
        $ride = Ride::factory()->arrived()->create();

        Sanctum::actingAs($ride->passenger);
        $this->postJson(route('api.v1.rides.start.store', $ride))->assertOk();

        Notification::assertNothingSent();
    });

    it('tells the passenger when the trip is done', function () {
        $driver = User::factory()->driver()->create();
        $ride = Ride::factory()->inProgress($driver)->create();

        Sanctum::actingAs($driver);
        $this->postJson(route('api.v1.driver.rides.completion.store', $ride))->assertOk();

        assertAlertSent($ride->passenger, AlertType::TripCompleted);
    });
});

describe('a cancelled ride', function () {
    it('tells the driver when the passenger cancels', function () {
        $driver = User::factory()->driver()->create();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($ride->passenger);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride))->assertOk();

        assertAlertSent($driver, AlertType::RideCancelled);
        Notification::assertNotSentTo($ride->passenger, Alert::class);
    });

    it('tells nobody when the passenger cancels a request that nobody took', function () {
        $ride = Ride::factory()->create();
        onlineDriver();

        Sanctum::actingAs($ride->passenger);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride))->assertOk();

        Notification::assertNothingSent();
    });

    it('tells the passenger and the other nearby drivers when the driver gives the pickup up', function () {
        $gaveUp = onlineDriver();
        $other = onlineDriver(7.8240, 123.4402);
        $ride = Ride::factory()->accepted($gaveUp)->create();

        Sanctum::actingAs($gaveUp);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'too_far'])->assertOk();

        assertAlertSent($ride->passenger, AlertType::DriverCancelled);
        assertAlertSent($other, AlertType::NewRideNearby);
        Notification::assertNotSentTo($gaveUp, Alert::class);
    });
});

describe('the reason of a cancel', function () {
    function alertBody(User $user, AlertType $type): string
    {
        $body = null;

        Notification::assertSentTo($user, Alert::class, function (Alert $alert) use ($type, &$body) {
            if ($alert->type !== $type) {
                return false;
            }

            $body = $alert->toArray($alert->ride->passenger)['body'];

            return true;
        });

        return $body;
    }

    it('shows the driver why the passenger cancelled', function () {
        $driver = User::factory()->driver()->create();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($ride->passenger);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'My brother is taking me.'])->assertOk();

        expect(alertBody($driver, AlertType::RideCancelled))
            ->toBe('This ride was cancelled before it started. Reason: My brother is taking me.');
    });

    it('does not make up a reason when the passenger gave none', function () {
        $driver = User::factory()->driver()->create();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($ride->passenger);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride))->assertOk();

        expect(alertBody($driver, AlertType::RideCancelled))->toBe('This ride was cancelled before it started.');
    });

    it('shows the passenger the words the driver wrote for Other', function () {
        $driver = onlineDriver();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($driver);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => 'other', 'note' => 'My tricycle ran out of fuel.'])->assertOk();

        expect(alertBody($ride->passenger, AlertType::DriverCancelled))
            ->toBe('We are looking for another driver for you. Reason: My tricycle ran out of fuel.');
    });

    it('shows the passenger a chosen reason in words', function (string $reason, string $words) {
        $driver = onlineDriver();
        $ride = Ride::factory()->accepted($driver)->create();

        Sanctum::actingAs($driver);
        $this->postJson(route('api.v1.rides.cancellation.store', $ride), ['reason' => $reason])->assertOk();

        expect(alertBody($ride->passenger, AlertType::DriverCancelled))->toEndWith("Reason: {$words}");
    })->with([
        'too far' => ['too_far', 'The pick-up point is too far'],
        'tricycle problem' => ['tricycle_problem', 'Tricycle problem'],
        'passenger not here' => ['passenger_not_here', 'The passenger was not at the pick-up point'],
    ]);
});

describe('an expired request', function () {
    it('tells the passenger that no driver was found', function () {
        $ride = Ride::factory()->create(['created_at' => now()->subMinutes(11)]);

        $this->artisan('rides:expire')->assertSuccessful();

        assertAlertSent($ride->passenger, AlertType::RideExpired);
    });

    it('tells nobody about a request that still has time', function () {
        Ride::factory()->create(['created_at' => now()->subMinutes(2)]);

        $this->artisan('rides:expire')->assertSuccessful();

        Notification::assertNothingSent();
    });
});
