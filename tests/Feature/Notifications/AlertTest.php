<?php

use App\AlertType;
use App\Models\DeviceToken;
use App\Models\Ride;
use App\Models\User;
use App\Notifications\Alert;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event;

describe('the alert list', function () {
    it('keeps the alert with its text and the ride', function () {
        $driver = User::factory()->driver()->create(['name' => 'Ramil Mendoza']);
        $ride = Ride::factory()->accepted($driver)->create();

        $ride->passenger->notify(new Alert(AlertType::RideAccepted, $ride));

        expect($ride->passenger->notifications()->sole()->data)->toBe([
            'type' => 'ride.accepted',
            'title' => 'Your driver is on the way',
            'body' => 'Ramil Mendoza accepted your request.',
            'ride_id' => $ride->id,
        ]);
    });

    it('keeps an alert that has no ride', function () {
        $driver = User::factory()->driver()->create();

        $driver->notify(new Alert(AlertType::DriverApproved));

        expect($driver->notifications()->sole()->data)->toMatchArray([
            'type' => 'driver.approved',
            'ride_id' => null,
        ]);
    });

    it('does not keep the "passenger is waiting" message', function () {
        $driver = User::factory()->driver()->create();

        $driver->notify(new Alert(AlertType::NewRideNearby, Ride::factory()->create()));

        $this->assertDatabaseCount('notifications', 0);
    });
});

describe('the open app', function () {
    it('hears the alert live on the private channel of the person, named after the type', function () {
        Event::fake([BroadcastNotificationCreated::class]);
        $ride = Ride::factory()->arrived()->create();

        $ride->passenger->notify(new Alert(AlertType::DriverArrived, $ride));

        Event::assertDispatched(BroadcastNotificationCreated::class, function (BroadcastNotificationCreated $event) use ($ride) {
            return collect($event->broadcastOn())->map->name->all() === ['private-App.Models.User.'.$ride->passenger_id]
                && $event->broadcastWith()['type'] === 'ride.driver-arrived'
                && $event->broadcastWith()['title'] === 'Your driver is here'
                && $event->broadcastWith()['ride_id'] === $ride->id;
        });
    });

    it('hears the "passenger is waiting" message too', function () {
        Event::fake([BroadcastNotificationCreated::class]);
        $driver = User::factory()->driver()->create();

        $driver->notify(new Alert(AlertType::NewRideNearby, Ride::factory()->create()));

        Event::assertDispatched(BroadcastNotificationCreated::class, fn (BroadcastNotificationCreated $event) => $event->broadcastWith()['type'] === 'ride.nearby');
    });
});

describe('the push notification', function () {
    it('goes to every phone of the person', function () {
        $ride = Ride::factory()->arrived()->create();
        DeviceToken::factory()->for($ride->passenger)->create(['token' => 'ExponentPushToken[phone-1]']);
        DeviceToken::factory()->for($ride->passenger)->create(['token' => 'ExponentPushToken[phone-2]']);
        DeviceToken::factory()->create(['token' => 'ExponentPushToken[someone-else]']);

        $ride->passenger->notify(new Alert(AlertType::DriverArrived, $ride));

        expect(push()->sent())->toBe([
            [
                'to' => 'ExponentPushToken[phone-1]',
                'title' => 'Your driver is here',
                'body' => 'Look for the tricycle at your pickup point.',
                'data' => ['type' => 'ride.driver-arrived', 'ride_id' => $ride->id],
            ],
            [
                'to' => 'ExponentPushToken[phone-2]',
                'title' => 'Your driver is here',
                'body' => 'Look for the tricycle at your pickup point.',
                'data' => ['type' => 'ride.driver-arrived', 'ride_id' => $ride->id],
            ],
        ]);
    });

    it('is also sent for the message that is not kept', function () {
        $ride = Ride::factory()->pickupAt(7.8231, 123.4402)->create(['pickup_label' => 'Balangasan']);
        $driver = User::factory()->driver()->create();
        DeviceToken::factory()->for($driver)->create();

        $driver->notify(new Alert(AlertType::NewRideNearby, $ride));

        expect(push()->sent())->toHaveCount(1);
        expect(push()->sent()[0])->toMatchArray([
            'title' => 'A passenger is waiting',
            'body' => 'Pickup at Balangasan.',
        ]);
    });

    it('is skipped for a person without a phone token', function () {
        $driver = User::factory()->driver()->create();

        $driver->notify(new Alert(AlertType::DriverApproved));

        expect(push()->sent())->toBe([]);
    });

    it('forgets the phones that the push service does not know any more', function () {
        $driver = User::factory()->driver()->create();
        DeviceToken::factory()->for($driver)->create(['token' => 'ExponentPushToken[gone]']);
        DeviceToken::factory()->for($driver)->create(['token' => 'ExponentPushToken[kept]']);
        push()->unregister(['ExponentPushToken[gone]']);

        $driver->notify(new Alert(AlertType::DriverApproved));

        expect($driver->deviceTokens()->pluck('token')->all())->toBe(['ExponentPushToken[kept]']);
    });

    it('does not put a phone number in the text', function () {
        $driver = User::factory()->driver()->create(['phone' => '+639171234567']);
        $ride = Ride::factory()->accepted($driver)->create();
        DeviceToken::factory()->for($ride->passenger)->create();

        $ride->passenger->notify(new Alert(AlertType::RideAccepted, $ride));

        expect(json_encode(push()->sent()))->not->toContain('+639171234567');
    });
});
