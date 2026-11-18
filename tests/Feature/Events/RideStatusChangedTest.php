<?php

use App\Events\RideStatusChanged;
use App\Listeners\SendRideAlerts;
use App\Models\Ride;
use App\Models\User;
use App\RideStatus;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

it('is announced at every step of a ride, from the request to the end', function () {
    Event::fake([RideStatusChanged::class]);

    $passenger = User::factory()->create();
    $driver = onlineDriver();

    Sanctum::actingAs($passenger);
    $rideId = $this->postJson(route('api.v1.rides.store'), rideRequestPayload())->assertCreated()->json('id');

    Sanctum::actingAs($driver);
    $this->postJson(route('api.v1.driver.rides.acceptance.store', $rideId))->assertCreated();
    $this->postJson(route('api.v1.driver.rides.arrival.store', $rideId))->assertOk();

    Sanctum::actingAs($passenger);
    $this->postJson(route('api.v1.rides.start.store', $rideId))->assertOk();

    Sanctum::actingAs($driver);
    $this->postJson(route('api.v1.driver.rides.completion.store', $rideId))->assertOk();

    $steps = Event::dispatched(RideStatusChanged::class)
        ->map(fn (array $arguments): array => [$arguments[0]->previous, $arguments[0]->status])
        ->all();

    expect($steps)->toBe([
        [null, RideStatus::Requested],
        [RideStatus::Requested, RideStatus::Accepted],
        [RideStatus::Accepted, RideStatus::Arrived],
        [RideStatus::Arrived, RideStatus::InProgress],
        [RideStatus::InProgress, RideStatus::Completed],
    ]);
});

it('is announced when a request is cancelled, given up or expires', function () {
    Event::fake([RideStatusChanged::class]);

    $cancelled = Ride::factory()->create();
    $gaveUp = Ride::factory()->accepted($driver = onlineDriver())->create();
    $expired = Ride::factory()->create(['created_at' => now()->subHour()]);

    Sanctum::actingAs($cancelled->passenger);
    $this->postJson(route('api.v1.rides.cancellation.store', $cancelled))->assertOk();

    Sanctum::actingAs($driver);
    $this->postJson(route('api.v1.rides.cancellation.store', $gaveUp), ['reason' => 'too_far'])->assertOk();

    $this->artisan('rides:expire')->assertSuccessful();

    Event::assertDispatched(RideStatusChanged::class, fn (RideStatusChanged $event) => $event->ride->is($cancelled)
        && $event->previous === RideStatus::Requested && $event->status === RideStatus::Cancelled);
    Event::assertDispatched(RideStatusChanged::class, fn (RideStatusChanged $event) => $event->ride->is($gaveUp)
        && $event->previous === RideStatus::Accepted && $event->status === RideStatus::Requested);
    Event::assertDispatched(RideStatusChanged::class, fn (RideStatusChanged $event) => $event->ride->is($expired)
        && $event->previous === RideStatus::Requested && $event->status === RideStatus::Expired);
});

it('is not announced when the action is refused', function () {
    Event::fake([RideStatusChanged::class]);

    $ride = Ride::factory()->completed()->create();

    Sanctum::actingAs($ride->driver);
    $this->postJson(route('api.v1.driver.rides.completion.store', $ride))->assertForbidden();

    Event::assertNotDispatched(RideStatusChanged::class);
});

it('keeps the status of the moment even if the ride moved on before the listener ran', function () {
    $ride = Ride::factory()->accepted()->create();
    $event = new RideStatusChanged($ride, RideStatus::Requested);

    $ride->update(['status' => RideStatus::Arrived]);

    expect($event->status)->toBe(RideStatus::Accepted);
    expect($event->broadcastWith())->toBe(['ride_id' => $ride->id, 'status' => 'accepted']);
});

it('is sent live to the passenger and the driver only', function () {
    $ride = Ride::factory()->accepted()->create();

    $channels = (new RideStatusChanged($ride, RideStatus::Requested))->broadcastOn();

    expect(collect($channels)->map->name->all())->toBe([
        'private-App.Models.User.'.$ride->passenger_id,
        'private-App.Models.User.'.$ride->driver_id,
    ]);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect((new RideStatusChanged($ride))->broadcastAs())->toBe('ride.updated');
});

it('is sent only to the passenger while nobody took the request', function () {
    $ride = Ride::factory()->create();

    $channels = (new RideStatusChanged($ride))->broadcastOn();

    expect(collect($channels)->map->name->all())->toBe(['private-App.Models.User.'.$ride->passenger_id]);
});

it('goes to the queue so a slow socket server never slows the ride down', function () {
    Queue::fake();

    RideStatusChanged::dispatch(Ride::factory()->create());

    Queue::assertPushed(BroadcastEvent::class, fn (BroadcastEvent $job) => $job->event instanceof RideStatusChanged);
    Queue::assertPushed(CallQueuedListener::class, fn ($job) => $job->class === SendRideAlerts::class);
});
