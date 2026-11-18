<?php

use App\Events\RideStatusChanged;
use App\Models\Ride;
use App\RideStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

it('expires the requests that waited longer than the limit', function () {
    $this->freezeTime();
    $stale = Ride::factory()->create(['created_at' => now()->subMinutes(10)]);
    $older = Ride::factory()->create(['created_at' => now()->subHour()]);

    $this->artisan('rides:expire')
        ->expectsOutput('2 ride request(s) expired.')
        ->assertSuccessful();

    expect($stale->fresh()->status)->toBe(RideStatus::Expired);
    expect($older->fresh()->status)->toBe(RideStatus::Expired);
});

it('keeps the requests that still have time', function () {
    $this->freezeTime();
    $fresh = Ride::factory()->create(['created_at' => now()->subMinutes(9)->subSeconds(59)]);

    $this->artisan('rides:expire')->expectsOutput('0 ride request(s) expired.')->assertSuccessful();

    expect($fresh->fresh()->status)->toBe(RideStatus::Requested);
});

it('leaves rides that already have a driver or are done', function (string $state, RideStatus $status) {
    $this->freezeTime();
    $ride = Ride::factory()->{$state}()->create(['created_at' => now()->subHour()]);

    $this->artisan('rides:expire')->assertSuccessful();

    expect($ride->fresh()->status)->toBe($status);
})->with([
    'accepted' => ['accepted', RideStatus::Accepted],
    'arrived' => ['arrived', RideStatus::Arrived],
    'on the way' => ['inProgress', RideStatus::InProgress],
    'completed' => ['completed', RideStatus::Completed],
    'cancelled' => ['cancelled', RideStatus::Cancelled],
]);

it('follows the time limit from the settings', function () {
    $this->freezeTime();
    config(['rides.request_expires_after_minutes' => 2]);
    $ride = Ride::factory()->create(['created_at' => now()->subMinutes(3)]);

    $this->artisan('rides:expire')->assertSuccessful();

    expect($ride->fresh()->status)->toBe(RideStatus::Expired);
});

it('is scheduled to run every minute', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'rides:expire'));

    expect($event)->not->toBeNull();
    expect($event->expression)->toBe('* * * * *');
    expect($event->withoutOverlapping)->toBeTrue();
});

it('announces each request it expired', function () {
    Event::fake([RideStatusChanged::class]);
    $stale = Ride::factory()->create(['created_at' => now()->subHour()]);
    Ride::factory()->create();

    $this->artisan('rides:expire')->assertSuccessful();

    Event::assertDispatchedTimes(RideStatusChanged::class, 1);
    Event::assertDispatched(RideStatusChanged::class, fn (RideStatusChanged $event) => $event->ride->is($stale)
        && $event->previous === RideStatus::Requested
        && $event->status === RideStatus::Expired);
});
