<?php

use App\AlertType;
use App\Models\User;
use App\Notifications\Alert;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\DatabaseNotification;

function alertMade(User $user, int $daysAgo, bool $read): DatabaseNotification
{
    $user->notify(new Alert(AlertType::RideAccepted));

    $alert = $user->notifications()->latest('created_at')->latest('id')->first();

    $alert->forceFill(['created_at' => now()->subDays($daysAgo), 'read_at' => $read ? now()->subDays($daysAgo) : null])->save();

    return $alert;
}

it('deletes the alerts that were read more than 30 days ago', function () {
    $user = User::factory()->create();
    $old = alertMade($user, 31, read: true);
    $recent = alertMade($user, 29, read: true);

    $this->artisan('alerts:prune')->expectsOutputToContain('Deleted 1 old alerts.')->assertSuccessful();

    expect(DatabaseNotification::find($old->id))->toBeNull();
    expect(DatabaseNotification::find($recent->id))->not->toBeNull();
});

it('keeps an alert that was not read until it is 90 days old', function () {
    $user = User::factory()->create();
    $waiting = alertMade($user, 60, read: false);
    $forgotten = alertMade($user, 91, read: false);

    $this->artisan('alerts:prune')->assertSuccessful();

    expect(DatabaseNotification::find($waiting->id))->not->toBeNull();
    expect(DatabaseNotification::find($forgotten->id))->toBeNull();
});

it('does nothing when there is nothing old', function () {
    $user = User::factory()->create();
    alertMade($user, 1, read: false);

    $this->artisan('alerts:prune')->expectsOutputToContain('Deleted 0 old alerts.')->assertSuccessful();

    expect(DatabaseNotification::count())->toBe(1);
});

it('is scheduled once a day', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'alerts:prune'));

    expect($event)->not->toBeNull();
    expect($event->expression)->toBe('30 3 * * *');
});
